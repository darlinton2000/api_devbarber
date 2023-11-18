<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['create', 'login', 'unauthorized']]);
    }

    /**
     * Cria o usuário e faz a autênticação
     *
     * @param Request $request
     * @return array
     */
    public function create(Request $request): array
    {
        $array = ['error' => ''];

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!$validator->fails()) {
            $name = $request->input('name');
            $email = $request->input('email');
            $password = $request->input('password');

            $emailExist = User::where('email', $email)->count();
            if ($emailExist === 0) {
                $newUser = new User();
                $newUser->name = $name;
                $newUser->email = $email;
                $newUser->password = password_hash($password, PASSWORD_DEFAULT);
                $newUser->save();

                $token = auth()->attempt([
                    'email' => $email,
                    'password' => $password
                ]);

                if (!$token) {
                    $array['error'] = 'Ocorreu um erro! #001';
                    return $array;
                }

                $info = auth()->user();
                $array['avatar'] = url('media/avatars/' . $info['avatar']);
                $array['data'] = $info;
                $array['token'] = $token;
            } else {
                $array['error'] = 'E-mail já cadastrado!';
                return $array;
            }
        } else {
            $array['error'] = 'Dados incorretos';
            return $array;
        }

        return $array;
    }

    /**
     * Login do usuário
     *
     * @param Request $request
     * @return array
     */
    public function login(Request $request): array
    {
        $array = ['error' => ''];

        $email = $request->input('email');
        $password = $request->input('password');

        $token = auth()->attempt([
            'email' => $email,
            'password' => $password
        ]);

        if (!$token) {
            $array['error'] = 'Usuário e/ou senha errados!';
            return $array;
        }

        $info = auth()->user();
        $array['avatar'] = url('media/avatars/' . $info['avatar']);
        $array['data'] = $info;
        $array['token'] = $token;

        return $array;
    }

    /**
     * Logout do usuário
     *
     * @return array
     */
    public function logout(): array
    {
        auth()->logout();
        return['error' => ''];
    }

    /**
     * Atualiza o token do usuário e retorna os dados
     *
     * @return array
     */
    public function refresh(): array
    {
        $array = ['error' => ''];

        $token = auth()->refresh();

        $info = auth()->user();
        $array['avatar'] = url('media/avatars/' . $info['avatar']);
        $array['data'] = $info;
        $array['token'] = $token;

        return $array;
    }

    public function unauthorized()
    {
        return response()->json([
            'error' => 'Não autorizado'
        ], 401);
    }
}
