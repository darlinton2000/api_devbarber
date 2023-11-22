<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\BarberAvailability;
use App\Models\BarberPhotos;
use App\Models\BarberServices;
use App\Models\BarberTestimonial;
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
//                    $time = $r = $rAdd;
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
}
