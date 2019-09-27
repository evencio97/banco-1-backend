<?php

namespace App\Http\Middleware;

use Closure;
use App\AccessToken;
use App\AuthClient;

use \Firebase\JWT\JWT;

class JWTAuth
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
        $jwt = $request->header('Authorization');
        try{
            //Comprobamos la validez del token
            $accestoken = AccessToken::where(['token' => $jwt, 'revoked' => 0])->first();
            if(!$accestoken) return response()->json(['success' => false, 'message' => 'Token de acceso invalido']);
            //Comprobamos que el token de acceso corresponde al ip de donde proviene el request
            $matchThese = ['id' => $accestoken->client_id, 'user_id' => $accestoken->user_id, 'ip' => $request->ip(), 'client_url'  => $request->root(), 'revoked' => 0];     
            $key = AuthClient::where($matchThese)->first();
            if(!$key) return response()->json(['success' => false, 'message' => 'Key token invalido'], 404);
            //Decodificamos el token y lo guardamos en los atributos del request
            $usr = JWT::decode($jwt, $key->secret, array('HS256'));
            $request->attributes->add(['user' => $usr->data]);
            return $next($request);
        }catch (\Throwable $e) {
            return response()->json([
                'error' => true,
                'token_exp' => true,
                'message' => 'Token expirado',
                'exception' => $e
            ], 500);
        }

    }
}
