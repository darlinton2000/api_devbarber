<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\BarberPhotos;
use App\Models\BarberServices;
use App\Models\BarberTestimonial;
use App\Models\UserAppointment;
use App\Models\UserFavorite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class BarberController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    /**
     * Gera barbeiros aleatoriamente no banco de dados
     *
     * @return array
     */
//    public function createRandom(): array
//    {
//        $array = ['error' => ''];
//
//        for($q=0; $q<15; $q++) {
//            $names = ['Bonieky', 'Paulo', 'Pedro', 'Amanda', 'Leticia', 'Gabriel'];
//            $lastNames = ['Silva', 'Larcerda', 'Diniz', 'Alvaro', 'Souza'];
//
//            $servicos = ['Corte', 'Pintura', 'Aparação', 'Enfeite'];
//            $servicos2 = ['Cabelo', 'Unha', 'Pernas', 'Sobrancelhas'];
//
//            $depos = [
//                'Todavia, o desenvolvimento contínuo de distintas formas de atuação talvez venha a ressaltar a relatividade dos índices pretendidos',
//                'O empenho em analisar o consenso sobre a necessidade de qualificação promove a alavancagem do sistema de participação geral.',
//                'É importante questionar o quanto a execução dos pontos do programa é uma das consequências dos paradigmas corporativos.',
//                'Desta maneira, o surgimento do comércio virtual desafia a capacidade de equalização de todos os recursos funcionais envolvidos.',
//                'Neste sentido, a valorização de fatores subjetivos prepara-nos para enfrentar situações atípicas decorrentes das diversas correntes de pensamento.'
//            ];
//
//            $newBarber = new Barber();
//            $newBarber->name = $names[rand(0, count($names)-1)] . ' ' . $lastNames[rand(0, count($lastNames)-1)];
//            $newBarber->avatar = rand(1, 4) . '.png';
//            $newBarber->stars = rand(2, 4) . '.' . rand(0,9);
//            $newBarber->latitude = '-23.5' . rand(0,9) . '30907';
//            $newBarber->longitude = '-46.6' . rand(0,9) . '82795';
//            $newBarber->save();
//
//            $ns = rand(3, 6);
//
//            for($w=0; $w<4; $w++) {
//                $newBarberPhoto = new BarberPhotos();
//                $newBarberPhoto->id_barber = $newBarber->id;
//                $newBarberPhoto->url = rand(1, 5) . '.png';
//                $newBarberPhoto->save();
//            }
//
//            for($w=0; $w<$ns; $w++) {
//                $newBarberService = new BarberServices();
//                $newBarberService->id_barber = $newBarber->id;
//                $newBarberService->name = $servicos[rand(0, count($servicos)-1)] . ' de ' . $servicos2[rand(0, count($servicos2)-1)];
//                $newBarberService->price = rand(1, 99) . '.' . rand(0, 100);
//                $newBarberService->save();
//            }
//
//            for($w=0; $w<3; $w++) {
//                $newBarberTertimonial = new BarberTestimonial();
//                $newBarberTertimonial->id_barber = $newBarber->id;
//                $newBarberTertimonial->name = $names[rand(0, count($names)-1)];
//                $newBarberTertimonial->rate = rand(2, 4) . '.' . rand(0, 100);
//                $newBarberTertimonial->body = $depos[rand(0, count($depos)-1)];
//                $newBarberTertimonial->save();
//            }
//
//            for($e=0; $e<4; $e++) {
//                $rAdd = rand(7, 10);
//                $hours = [];
//                for($r=0; $r<8; $r++) {
//                    $time = $r + $rAdd;
//                    if ($time < 10) {
//                        $time = '0' . $time;
//                    }
//                    $hours[] = $time . ':00';
//                }
//                $newBarberAvail = new BarberAvailability();
//                $newBarberAvail->id_barber = $newBarber->id;
//                $newBarberAvail->weekday = $e;
//                $newBarberAvail->hours = implode(',', $hours);
//                $newBarberAvail->save();
//            }
//        }
//
//        return $array;
//    }

    /**
     * Busca o $address na API do Google
     *
     * @param $address
     * @return mixed
     */
    private function searchGeo($address)
    {
        $address = urlencode($address);
        $key = env('MAPS_KEY', null);

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . $address . '?key=' . $key;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * Lista os barbeiros
     *
     * @param Request $request
     * @return array
     */
    public function list(Request $request): array
    {
        $array = ['error' => ''];

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $city = $request->input('city');
        $offset = $request->input('offset');

        if (!$offset) {
            $offset = 0;
        }

        if (!empty($city)) {
            $res = $this->searchGeo($city);

            if (count($res['results']) > 0) {
                $lat = $res['results'][0]['geometry']['location']['lat'];
                $lng = $res['results'][0]['geometry']['location']['lng'];
            }
        } elseif (!empty($lat) && !empty($lng)) {
            $res = $this->searchGeo($lat . ',' . $lng);

            if (count($res['results']) > 0) {
                $city = $res['results'][0]['formatted_address'];
            }
        } else {
            $lat = '-23.5630907';
            $lng = '-46.6682795';
            $city = 'São Paulo';
        }

        $barbers = Barber::select(Barber::raw('*, SQRT(
            POW(69.1 * (latitude - ' . $lat . '), 2) +
            POW(69.1 * (' . $lng . ' - longitude) * COS(latitude / 57.3), 2)) AS distance'))
            ->havingRaw('distance < ?', [10])
            ->orderBy('distance', 'ASC')
            ->offset($offset)
            ->limit(5)
            ->get();

        foreach ($barbers as $bkey => $bvalue) {
            $barbers[$bkey]['avatar'] = url('media/avatars/' . $barbers[$bkey]['avatar']);
        }

        $array['data'] = $barbers;
        $array['loc'] = 'Sao Paulo';

        return $array;
    }

    /**
     * Retorna os dados do barbeiro de acordo com o id
     *
     * @param int $id
     * @return array
     */
    public function one(int $id = 0): array
    {
        $array = ['error' => ''];

        $barber = Barber::find($id);

        if ($barber) {
            $barber['avatar'] = url('media/avatars/' . $barber['avatar']);
            $barber['favorited'] = false;
            $barber['photos'] = [];
            $barber['services'] = [];
            $barber['testimonials'] = [];
            $barber['available'] = [];

            // Verificando favorito
            $cFavorite = UserFavorite::where('id_user', $this->loggedUser->id)
                ->where('id_barber', $barber->id)
                ->count();
            if ($cFavorite > 0) {
                $barber['favorited'] = true;
            }

            // Fotos do barbeiro
            $barber['photos'] = BarberPhotos::select(['id', 'url'])
                ->where('id_barber', $barber->id)
                ->get();
            foreach ($barber['photos'] as $bpkey => $bpvalue) {
                $barber['photos'][$bpkey]['url'] = url('media/uploads/' . $barber['photos'][$bpkey]['url']);
            }

            // Servicos do barbeiro
            $barber['services'] = BarberServices::select(['id', 'name', 'price'])
                ->where('id_barber', $barber->id)
                ->get();

            // Depoimentos do barbeiro
            $barber['testimonials'] = BarberTestimonial::select(['id', 'name', 'rate', 'body'])
                ->where('id_barber', $barber->id)
                ->get();

            // Disponibilidade do barbeiro
            $availabity = [];

            // Pegando a disponibilidade crua
            $avails = BarberAvailability::where('id_barber', $barber->id)->get();
            $availWeekdays = [];
            foreach ($avails as $item) {
                $availWeekdays[$item['weekday']] = explode(',', $item['hours']);
            }

            // Pegar os agendamentos dos próximoss 20 dias
            $appointments = [];
            $appQuery = UserAppointment::where('id_barber', $barber->id)
                ->whereBetween('ap_datetime', [
                    date('Y-m-d') . ' 00:00:00',
                    date('Y-m-d', strtotime('+20 days')) . ' 23:59:59'
                ])
                ->get();
            foreach ($appQuery as $appItem) {
                $appointments[] = $appItem['ap_datetime'];
            }

            // Gerar disponibilidade real
            for ($q=0;$q<20;$q++) {
                $timeItem = strtotime('+'.$q.' days');
                $weekday = date('w', $timeItem);

                if (in_array($weekday, array_keys($availWeekdays))) {
                    $hours = [];

                    $dayItem = date('Y-m-d', $timeItem);

                    foreach ($availWeekdays[$weekday] as $hourItem) {
                        $dayFormated = $dayItem.' '.$hourItem.':00';
                        if (!in_array($dayFormated, $appointments)) {
                            $hours[] = $hourItem;
                        }
                    }

                    if (count($hours) > 0) {
                        $availabity[] = [
                            'date' => $dayItem,
                            'hours' => $hours
                        ];
                    }
                }
            }

            $barber['available'] = $availabity;

            $array['data'] = $barber;
        } else {
            $array['error'] = 'Barbeiro não existe';
            return $array;
        }

        return $array;
    }
}
