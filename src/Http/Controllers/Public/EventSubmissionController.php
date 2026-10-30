<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SubmitEventRequest;
use App\Repositories\Contracts\CategoryRepository;
use App\Services\EventSubmissionService;
use App\Services\RevisionTokenService;
use App\Support\RateLimiter;

final class EventSubmissionController extends Controller
{
    public function __construct(
        View $view,
        private readonly EventSubmissionService $submissions,
        private readonly RevisionTokenService $tokens,
        private readonly CategoryRepository $categories,
        private readonly RateLimiter $limiter,
    ) {
        parent::__construct($view);
    }

    public function show(Request $request): Response
    {
        return $this->renderPage('submissions/form', [
            'title' => 'Ajukan Kegiatan',
            'event' => null,
            'token' => '',
            'revision' => false,
            'categories' => $this->categories->allActive(),
        ]);
    }

    public function submit(Request $request): Response
    {
        $this->throttle($request);
        $data = (new SubmitEventRequest($request))->validate();
        $poster = $request->file('poster');

        if ($poster === null) {
            throw new \InvalidArgumentException('Poster wajib diunggah.');
        }

        $this->submissions->submit($data, $poster, $request->file('guidebook'));
        app()->session()->flash('status', 'Pengajuan diterima. Tim CompHub akan meninjau kegiatan Anda.');

        return $this->redirect(url('/ajukan/berhasil'));
    }

    public function success(Request $request): Response
    {
        return $this->renderPage('submissions/success', [
            'title' => 'Pengajuan Diterima',
        ]);
    }

    public function edit(Request $request): Response
    {
        $this->throttle($request);
        $token = (string) (($request->attribute('route') ?? [])['token'] ?? '');
        $event = $this->tokens->resolve($token);

        if ($event === null) {
            throw HttpException::notFound();
        }

        return $this->privatePage('submissions/form', [
            'title' => 'Perbaiki Pengajuan',
            'event' => $event,
            'token' => $token,
            'revision' => true,
            'categories' => $this->categories->allActive(),
        ]);
    }

    public function update(Request $request): Response
    {
        $this->throttle($request);
        $token = (string) (($request->attribute('route') ?? [])['token'] ?? '');
        $event = $this->tokens->resolve($token);

        if ($event === null) {
            throw HttpException::notFound();
        }

        $request->setAttribute('revision_event', true);
        $request->setAttribute('has_existing_guidebook', trim((string) ($event['guidebook_path'] ?? '')) !== '');

        $data = (new SubmitEventRequest($request))->validate();
        $this->submissions->revise($event, $data, $request->file('poster'), $request->file('guidebook'));
        $this->tokens->revoke((int) $event['id']);
        app()->session()->flash('status', 'Perbaikan diterima. Pengajuan kembali masuk antrean peninjauan.');

        return $this->redirect(url('/ajukan/berhasil'));
    }

    private function throttle(Request $request): void
    {
        $key = 'submission-token:' . $request->ip();
        $limit = (array) Config::get('security.rate_limits.submission-token', ['attempts' => 30, 'decay' => 900]);

        if ($this->limiter->tooManyAttempts($key, (int) $limit['attempts'])) {
            throw new HttpException(429);
        }

        $this->limiter->hit($key, (int) $limit['decay']);
    }

    /** @param array<string, mixed> $data */
    private function privatePage(string $template, array $data): Response
    {
        return $this->renderPage($template, $data)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
