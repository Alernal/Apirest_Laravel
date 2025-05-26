<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends BaseController
{
    public function show()
    {
        $user = Auth::user();

        return $this->sendResponse($user, 'Información del usuario');
    }
}
