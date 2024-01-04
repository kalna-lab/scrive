<?php

namespace KalnaLab\Scrive\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KalnaLab\Scrive\Scrive;

class ScriveController extends Controller
{
    public function authenticate(Request $request)
    {
        $transactionId = $request->get('transaction_id');
        return Scrive::authenticate($transactionId);
    }
}
