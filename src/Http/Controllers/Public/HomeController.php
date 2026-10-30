<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CategoryRepository;
use App\Repositories\Contracts\EventRepository;

final class HomeController extends Controller
{
    public function __construct(
        View $view,
        private readonly EventRepository $events,
        private readonly CategoryRepository $categories,
    ) {
        parent::__construct($view);
    }

    public function __invoke(Request $request): Response
    {
        return $this->renderPage('pages/home', [
            'title' => 'Direktori Lomba Kampus',
            'latest' => $this->events->findLatestOpen(6),
            'deadlineSoon' => $this->events->findDeadlineSoon(3),
            'categories' => $this->categories->allActive(),
            'openCount' => $this->events->countOpen(),
        ]);
    }
}
