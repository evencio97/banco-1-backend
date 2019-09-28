<?php

use App\Bank;
namespace App\Http\Middleware;
use Illuminate\Support\Facades\Crypt;

use Closure;

class banksAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $bank_key = $request->header('key');
        if (!$bank_key) return response()->json(['success' => false, 'message' => 'Key header is required']);
        try{
            //Comprobamos la validez del token
            // $encrypted = Crypt::encryptString('Hello world.');
            $bank_key = Crypt::decryptString($bank_key);
            // Validamos el contenido de la clave
            $bank = Bank::where('bnk_key', $bank_key)->first();
            if(!$bank) return response()->json(['success' => false, 'message' => 'Key value is invalid']);
            // Añadimos la data del banco
            $request->attributes->add(['user' => $bank]);
            return $next($request);
        }catch (\Throwable $e) {
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please check if the key value is correct', 'exception' => $e
            ], 500);
        }
    }
}
