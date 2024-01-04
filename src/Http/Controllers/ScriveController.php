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
        return Scrive::authorize($provider);
    }

    public function authenticate(Request $request)
    {
        $transactionId = $request->get('transaction_id');
        return Scrive::authenticate($transactionId);
    }
}
