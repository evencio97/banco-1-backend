<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class BaseController extends Controller
{
    protected function validateRequest(Request $request, $rules = [], $messages = [])
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return $validator->errors()->all();
        }
        return [];
    }

    protected function getMessagesErrors($errors = []) {
        $messagesString = '';
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $messagesString .= $error . (count($errors)>1? ', ':'');
            }
        }
        return $messagesString;
    }
}
