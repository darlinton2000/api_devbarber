<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\BarberServices;
use App\Models\User;
use App\Models\UserAppointment;
use App\Models\UserFavorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    /**
     * Retorna os dados do usuário logado
     *
     * @return array
     */
    public function read(): array
    {
        $array = ['error' => ''];

        $info = $this->loggedUser;
        $info['avatar'] = url('media/avatars/' . $info['avatar']);
        $array['data'] = $info;

        return $array;
    }

    /**
     * Adiciona/remove o barbeiro como favorito
     *
     * @param Request $request
     * @return array
     */
    public function toggleFavorite(Request $request): array
    {
        $array = ['error' => ''];

        $idBarber = $request->input('barber');
        $checkBarber = Barber::find($idBarber);

        if ($checkBarber) {
            $fav = UserFavorite::select()
                ->where('id_user', $this->loggedUser->id)
                ->where('id_barber', $idBarber)
                ->first();

            if ($fav) {
                $fav->delete();
                $array['have'] = false;
            } else {
                $newFav = new UserFavorite();
                $newFav->id_user = $this->loggedUser->id;
                $newFav->id_barber = $idBarber;
                $newFav->save();
                $array['have'] = true;
            }
        } else {
            $array['error'] = 'Barbeiro inexistente';
        }

        return $array;
    }

    /**
     * Lista os barbeiros favoritos do usuário autênticado
     *
     * @return array
     */
    public function getFavorites(): array
    {
        $array = ['error' => '', 'list' => []];

        $favs = UserFavorite::select()
            ->where('id_user', $this->loggedUser->id)
            ->get();

        if ($favs) {
            foreach ($favs as $fav) {
                $barber = Barber::find($fav['id_barber']);
                $barber['avatar'] = url('media/avatars/' . $barber['avatar']);
                $array['list'][] = $barber;
            }
        }

        return $array;
    }

    /**
     * Lista os agendamentos do usuário autênticado
     *
     * @return array
     */
    public function getAppointments(): array
    {
        $array = ['error' => '', 'list' => []];

        $apps = UserAppointment::select()
            ->where('id_user', $this->loggedUser->id)
            ->orderBy('ap_datetime', 'DESC')
            ->get();

        if ($apps) {
            foreach ($apps as $app) {
                $barber = Barber::find($app['id_barber']);
                $barber['avatar'] = url('media/avatars' . $barber['avatar']);

                $service = BarberServices::find($app['id_service']);

                $array['list'][] = [
                    'id' => $app['id'],
                    'datetime' => $app['ap_datetime'],
                    'barber' => $barber,
                    'service' => $service
                ];
            }
        }

        return $array;
    }

    /**
     * Atualiza as informações do usuário autênticado
     *
     * @param Request $request
     * @return array
     */
    public function update(Request $request): array
    {
        $array = ['error' => ''];

        $rules = [
            'name' => 'min:2',
            'email' => 'email|unique:users',
            'password' => 'same:password_confirm',
            'password_confirm' => 'same:password'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $array['error'] = $validator->messages();
            return $array;
        }

        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');
        $password_confirm = $request->input('password_confirm');

        $user = User::find($this->loggedUser->id);

        if ($name) {
            $user->name = $name;
        }

        if ($email) {
            $user->email = $email;
        }

        if ($password) {
            $user->password = password_hash($password, PASSWORD_DEFAULT);
        }

        $user->save();

        return $array;
    }
}
