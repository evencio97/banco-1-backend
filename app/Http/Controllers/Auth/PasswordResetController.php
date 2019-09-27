<?php

namespace App\Http\Controllers\Auth;

use Mail;
use App\JuristicUser;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Str;
use App\User;
use Carbon\Carbon;
use App\PasswordReset;
use Illuminate\Support\Facades\DB;

class PasswordResetController extends BaseController
{
    /**
     * Create token password reset
     *
     * @param  [string] email
     * @return [string] message
     */
    public function create(Request $request)
    {
        $rules = [
            'email' => 'required|string|email',
        ];
        $errors = $this->validateRequest($request, $rules);
        if (count($errors)) {
            return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
        }
        try {
            DB::beginTransaction();
            $user = $request->input('type') == 1 ?  User::where('email', $request->email)->first() : JuristicUser::where('jusr_email', $request->email)->first();
            if (!$user)
                return response()->json([
                    'message' => 'No podemos encontrar ninguna cuenta asociada a ese correo electrónico.'
                ], 404);

            if ($user->active == 0) {
                $user->activation_token = Str::random(60);
                $user->save();
                if (isset($user->jusr_rif)) {
                    $url = env('CLIENT_URL') . 'confirm-account/' . $user->activation_token;
                    $data = array('jusr_company' => $user->jusr_company, 'admin_name' => $user->first_name, 'admin_surname' => $user->first_surname, 'url' => $url, 'client_url' => env('CLIENT_URL'));
                    Mail::send('emails.juristic_activation', $data, function ($message) use ($user) {
                        $message->from(env('MAIL_USERNAME'), 'Banco 1');
                        $message->replyTo(env('MAIL_USERNAME'), 'Banco 1');
                        $message->to($user->jusr_email, $user->jusr_company)->subject('Confirma tu cuenta');
                    });
                } else {
                    $url = env('CLIENT_URL') . 'confirm-account/' . $user->activation_token;
                    $data = array('first_name' => $user->first_name, 'middle_name' => $user->middle_name, 'first_surname' => $user->first_surname, 'second_surname' => $user->second_surname, 'url' => $url, 'client_url' => env('CLIENT_URL'));
                    Mail::send('emails.usr_activation', $data, function ($message) use ($user) {
                        $message->from(env('MAIL_USERNAME'), 'Banco 1');
                        $message->replyTo(env('MAIL_USERNAME'), 'Banco 1');
                        $message->to($user->email, $user->first_name . ' ' . $user->first_surname)->subject('Confirma tu cuenta');
                    });
                }
                return response()->json([
                    'message' => 'Esta cuenta todavía no fue activada, se le acaba de mandar un nuevo correo activación, seguidamente vuelva a solicitar una nueva contraseña'
                ], 404);
            }

            $token_reset = Str::random(60);
            $passwordReset = PasswordReset::updateOrCreate(
                ['email' => isset($user->user_ci) ? $user->email:$user->jusr_email],
                [
                    'email' => isset($user->user_ci) ? $user->email:$user->jusr_email,
                    'token' => $token_reset
                ]
            );

            if (!$passwordReset)
                return response()->json([
                    'message' => 'Problemas al generar el token de restauración de contraseña'
                ]);

            $url = env('CLIENT_URL') . 'reset-request/' . $token_reset;
            if ($request->input('type') == 1) {
                $data = array('name' => $user->first_name, 'lastname' => $user->first_surname, 'url' => $url, 'client_url' => env('CLIENT_URL'));
                Mail::send('emails.reset_request', $data, function ($message) use ($user) {
                    $message->from(env('MAIL_USERNAME'), 'Banco 1');
                    $message->replyTo(env('MAIL_USERNAME'), 'Banco 1');
                    $message->to($user->email, $user->first_name . ' ' . $user->first_surname)->subject('Recuperar contraseña');
                });
            } else {
                $data = array('name' => $user->jusr_company, 'lastname' => $user->jusr_rif, 'url' => $url, 'client_url' => env('CLIENT_URL'));
                Mail::send('emails.reset_request', $data, function ($message) use ($user) {
                    $message->from(env('MAIL_USERNAME'), 'Banco 1');
                    $message->replyTo(env('MAIL_USERNAME'), 'Banco 1');
                    $message->to($user->jusr_email, $user->jusr_company . ' ' . $user->jusr_rif)->subject('Recuperar contraseña');
                });
            }
            DB::commit();
            return response()->json([
                'message' => 'Se le ha enviado un correo electrónico con un enlace para recuperar su contraseña!'
            ]);
        } catch (Swift_TransportException $a) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $a
            ], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }
    /**
     * Find token password reset
     *
     * @param  [string] $token
     * @return [string] message
     * @return [json] passwordReset object
     */
    public function find($token)
    {
        try {
            DB::beginTransaction();
            $passwordReset = PasswordReset::where('token', $token)
                ->first();
            if (!$passwordReset)
                return response()->json([
                    'message' => 'El token es invalido.'
                ], 404);
            if (Carbon::parse($passwordReset->updated_at)->addMinutes(720)->isPast()) {
                $passwordReset->delete();
                return response()->json([
                    'message' => 'El token ha caducado.'
                ], 404);
            }
            $user = User::where('email', $passwordReset->email)->first();
            if (!$user) {
                $user = JuristicUser::where('jusr_email', $passwordReset->email)->first();
                if (!$user)
                    return response()->json([
                        'message' => 'No podemos conseguir un usuario con ese correo electrónico.'
                    ], 404);
            }
            DB::commit();
            return response()->json(['resetToken' => $passwordReset, 'security_question' => $user->q_recovery]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }
    /**
     * Reset password
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [string] password_confirmation
     * @param  [string] token
     * @return [string] message
     * @return [json] user object
     */
    public function reset(Request $request)
    {
        $rules = [
            'password' => 'required|string|confirmed',
            'a_recovery' => 'required|string',
            'token' => 'required|string'
        ];
        $errors = $this->validateRequest($request, $rules);
        if (count($errors)) {
            return response()->json(['success' => false, 'message' => $this->getMessagesErrors($errors)], 422);
        }
        try {
            DB::beginTransaction();

            $passwordReset = PasswordReset::where('token', $request->token)->first();
            if (!$passwordReset)
                return response()->json([
                    'message' => 'Este token para reiniciar contraseña es invalido.'
                ], 404);
            $user = User::where('email', $passwordReset->email)->first();
            if (!$user) {
                $user = JuristicUser::where('jusr_email', $passwordReset->email)->first();
                if (!$user)
                    return response()->json([
                        'message' => 'No podemos conseguir un usuario con ese correo electrónico.'
                    ], 404);
            }

            if (strtolower($request->a_recovery) != strtolower($user->a_recovery))
                return response()->json([
                    'message' => 'La respuesta a la pregunta de seguridad es invalida.'
                ], 404);
            //Save user with new password    
            $user->password = bcrypt($request->password);
            $user->save();
            //Delete request for reset password
            $passwordReset->delete();
            //Mail
            if($user->jusr_rif){
                $data = array('name' => $user->jusr_company, 'lastname' => $user->jusr_rif, 'client_url' => env('CLIENT_URL'));
                Mail::send('emails.reset_success', $data, function ($message) use ($user) {
                    $message->from(env('MAIL_USERNAME'), 'Banco 1');
                    $message->replyTo(env('MAIL_USERNAME'), 'Banco 1');
                    $message->to($user->jusr_email, $user->jusr_company . ' ' . $user->jusr_rif)->subject('Solicitud de cambio de contraseña exitoso');
                });
            }else{
                $data = array('name' => $user->first_name, 'lastname' => $user->first_surname, 'client_url' => env('CLIENT_URL'));
                Mail::send('emails.reset_success', $data, function ($message) use ($user) {
                    $message->from(env('MAIL_USERNAME'), 'Banco 1');
                    $message->replyTo(env('MAIL_USERNAME'), 'Banco 1');
                    $message->to($user->email, $user->first_name . ' ' . $user->first_surname)->subject('Solicitud de cambio de contraseña exitoso');
                });
            }

            DB::commit();
            return response()->json(['user' => $user, 'message' => 'Se ha actualizado la contraseña satisfactoriamente, inicie sesión ahora.']);
        } catch (Swift_TransportException $a) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $a
            ], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'An error has occurred, please try again later',
                'exception' => $e
            ], 500);
        }
    }
}
