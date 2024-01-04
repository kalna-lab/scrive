<?php

namespace KalnaLab\Scrive\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KalnaLab\Scrive\Resources\AuthProviders\dkMitID;
use KalnaLab\Scrive\Scrive;

class ScriveController extends Controller
{
    public function dkMitID()
    {
        $provider = new dkMitID();
        $scrive = new Scrive();
        return $scrive->authorize($provider);
    }

    /**
     * @throws \Exception
     */
    public function authenticate(Request $request)
    {
        $transactionId = $request->get('transaction_id');
        $scrive = new Scrive();
        return $scrive->authenticate($transactionId);
    }
}
