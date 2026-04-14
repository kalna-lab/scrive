<?php

declare(strict_types=1);

namespace KalnaLab\Scrive\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KalnaLab\Scrive\Exceptions\ScriveException;
use KalnaLab\Scrive\Resources\AuthProviders\dkMitID;
use KalnaLab\Scrive\Scrive;

class ScriveController extends Controller
{
    public function dkMitID(Request $request): RedirectResponse
    {
        if ($request->session()->exists('ScriveCompletionData')) {
            return $this->redirectSuccessfulAuth();
        }

        $accessUrl = (new Scrive)->authorize(new dkMitID);

        return redirect($accessUrl);
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $transactionId = (string)$request->input('transaction_id', '');
        if ($transactionId === '') {
            return redirect((string)config('scrive.auth.failed-path'));
        }

        try {
            $success = (new Scrive)->authenticate($transactionId);
        } catch (ScriveException) {
            return redirect((string)config('scrive.auth.failed-path'));
        }

        return $success
            ? $this->redirectSuccessfulAuth()
            : redirect((string)config('scrive.auth.failed-path'));
    }

    private function redirectSuccessfulAuth(): RedirectResponse
    {
        $intendedUrl = session('intended-url');
        if (is_string($intendedUrl) && $intendedUrl !== '') {
            return redirect($intendedUrl);
        }

        return redirect((string)config('scrive.auth.landing-path'));
    }
}
