<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\ValidationException;

require dirname(__DIR__) . '/bootstrap/app.php';

$request = Request::capture();

try {
    app()->handle($request)->send();
} catch (ValidationException $exception) {
    if ($request->wantsJson()) {
        App::json(['message' => 'Data tidak valid.', 'errors' => $exception->errors()], 422)->send();
    } else {
        app()->session()->flash('_errors', $exception->errors());
        app()->session()->flash('_old', $exception->old());
        App::redirect($request->header('referer') ?: '/')->send();
    }
} catch (HttpException $exception) {
    app()->renderError($exception->statusCode, $exception->getMessage())->send();
} catch (Throwable $exception) {
    error_log($exception::class . ': ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());

    if (App::config('app.debug')) {
        App::html('<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>', 500)->send();
    } else {
        app()->renderError(500)->send();
    }
}
