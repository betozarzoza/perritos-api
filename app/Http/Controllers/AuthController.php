<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mail;
use Carbon\Carbon;
use App\Models\User;
//  use Conekta\Conekta;

class AuthController extends Controller
{

    /**
     * Create user
     *
     * @param  [string] name
     * @param  [string] email
     * @param  [string] password
     * @param  [string] password_confirmation
     * @return [string] message
     */
    public function signup(Request $request)
    {
        //  Conekta::setApiKey(env('CONEKTA_API_KEY'));
        $request->validate([
            'name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|confirmed'
        ]);
        $user = new User([
            'name' => $request->name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);
        /*
        $customer = \Conekta\Customer::create(
          [
            'name'  => $request->name . ' ' . $request->last_name,
            'email' => $request->email
          ]
        );
        $user->conekta_id = $customer->id;
        */
        $user->save();
        return response()->json([
            'message' => 'Successfully created user!'
        ], 201);
    }

    /**
     * Login user and create token
     *
     * @param  [string] email
     * @param  [string] password
     * @param  [boolean] remember_me
     * @return [string] access_token
     * @return [string] token_type
     * @return [string] expires_at
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);
        $credentials = request(['email', 'password']);
        if(!Auth::attempt($credentials))
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        $user = $request->user();
        $tokenResult = $user->createToken('Personal Access Token');
        $token = $tokenResult->token;
        if ($request->remember_me)
            $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();
        return response()->json([
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString()
        ]);
    }

    /**
     * Reset password
     *
     * @return [object] status and message
     */
    public function resetPassword(Request $request)
    {
      if (DB::table('password_resets')->where('email', $request->email)->where('token', $request->token)->exists()) {
        $userData = User::where('email', $request->email)->first();
        $user = User::find($userData->id);
        $user->password = bcrypt($request->password);
        $user->save();
        DB::table('password_resets')->where('email', $request->email)->delete();
        $result_array = array(
            'type'      => 'success',
            'message'    => 'Contrasena cambiada correctamente.',
        );
        return json_encode($result_array);
      }
    }

    /**
     * Reset password token
     *
     * @return [string] reset link
     */
    public function createResetPasswordToken(Request $request)
    {
      if(User::where('email', $request->email)->exists()){
        if (DB::table('password_resets')->where('email', $request->email)->exists()) {
          DB::table('password_resets')->where('email', $request->email)->delete();
        }
        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => Str::random(60),
            'created_at' => Carbon::now()
        ]);
        $tokenData = DB::table('password_resets')->where('email', $request->email)->first();
        $email = Mail::send('emails.resetpassword', ['token' => $tokenData->token], function ($m) use ($request) {
            $m->from('hello@forgotpassword.com', 'Company name');
            $m->to($request->email)->subject('Recuperar contraseña');
        });
        $result_array = array(
            'type'      => 'success',
            'message'    => 'Direccion enviada al correo.',
        );
        return json_encode($result_array);
      } else {
        $result_array = array(
            'type'      => 'error',
            'message'    => 'No se encontro el correo.',
        );
        return json_encode($result_array);
      }
    }
    /**
     * Logout user (Revoke the token)
     *
     * @return [string] message
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Get the authenticated User
     *
     * @return [json] user object
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
