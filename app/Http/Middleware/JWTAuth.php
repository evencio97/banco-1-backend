<?php

namespace App\Http\Middleware;

use Closure;
use App\User;
use App\AuthClient;
use App\AccessToken;
use App\JuristicUser;

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
        if (!$jwt) return response()->json(['success' => false, 'token_fail' => true, 'message' => 'Authorization header is required', 'headers' => $request->header()], 401);
        try{
            //Comprobamos la validez del token
            $accestoken = AccessToken::where(['token' => $jwt, 'revoked' => 0])->first();
            if(!$accestoken) return response()->json(['success' => false, 'token_fail' => true, 'message' => 'Token de acceso invalido'], 401);
            //Comprobamos que el token de acceso corresponde al ip de donde proviene el request
            $matchThese = ['id' => $accestoken->client_id, 'user_id' => $accestoken->user_id, 'ip' => $request->ip(), 'client_url'  => $request->root(), 'revoked' => 0];     
            $key = AuthClient::where($matchThese)->first();
            if(!$key) return response()->json(['success' => false, 'token_fail' => true, 'message' => 'Key token invalido'], 401);
            //Decodificamos el token y lo guardamos en los atributos del request
            $usr = JWT::decode($jwt, $key->secret, array('HS256'));
            $usr = collect($usr->data);
            if ($usr->get('jusr_rif')){
                $request['user'] = JuristicUser::find($usr['id']);
            } else{
                $request['user'] = User::find($usr['id']);
            }
            return $next($request);
        } catch (\Firebase\JWT\ExpiredException $e) {
            // Token expirado
            $tokenUpdate = AccessToken::where('token', $jwt)->update(['revoked' => 1]);
            
            return response()->json([
                'success' => false, 'token_exp' => true, 'message' => 'La sesion ha expirado, por favor inicie sesion nuevamente', 'exception' => $e
            ], 500);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false, 'message' => 'An error has occurred, please try again later', 'exception' => $e
            ], 500);
        }
    }
}
