<?php

namespace KalnaLab\Scrive\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use KalnaLab\Scrive\Resources\AuthProviders\dkMitID;
use KalnaLab\Scrive\Scrive;

class ScriveController extends Controller
{
    public function dkMitID(Request $request): RedirectResponse
    {
        if ($request->session()->exists('ScriveCompletionData')) {
            return redirect(config('scrive.landing-path'));
        }

        $provider = new dkMitID();
        $scrive = new Scrive();
        $accessUrl = $scrive->authorize($provider);
        return redirect($accessUrl);
    }

    /**
     * @throws \Exception
     */
    public function authenticate(Request $request): RedirectResponse
    {
        $transactionId = $request->get('transaction_id');
        $scrive = new Scrive();
        if ($scrive->authenticate($transactionId)) {
            return redirect(config('scrive.landing-path'));
        }
        return redirect(config('scrive.failed-path'));
    }
}
