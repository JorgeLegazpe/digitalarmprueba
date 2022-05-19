<?php

namespace App\Http\Controllers\Deals;

class DealController extends Controller
{
    public function checkExistActiveDealPrice(Request $request)
    {
        //Validamos la llegada de valores a la función
        if(is_null($request->post('id_deal')) || is_null($request->post('start_date'))){
            syslog(LOG_INFO, "Los valores no llegan correctamente")
            return collect(["code" => 500])->toJson();
            exit;
        }else{
            // Cargamos los valores a nuestras variables
            $prices = Deal::find($request->post('id_deal'))->prices()->get();
            $newStartDate = Carbon::parse($request->post('start_date'))->format('Y-m-d');
        }

        //Comprobamos que tenemos algo en la variable prices
        if( !is_null($prices) ) {
            return $this->checkIfTheDateIslowerOrLaterOrEqualToTheRecordInTheTable($newStartDate, $prices->first())->toJson();
        } else {
            syslog(LOG_INFO, "No recibimos el valor de prices para el id_deal solicitado")
            return collect([ "code" => 500 ])->toJson();
        }

        $newDateBetweenSomePrice = Price::whereRaw('"'.Carbon::parse($request->post('start_date'))->format('Y-m-d').'" BETWEEN arm_deals_prices.start_date AND arm_deals_prices.end_date')->where('id_deal', $request->post('id_deal'));

        if( !is_null($newDateBetweenSomePrice) ) {
            $nextPriceToNew = Price::where('id_deal', $request->post('id_deal'))->where('start_date', '>=', $newStartDate)->orderBy('start_date', 'ASC')->get()->first();
            $newDateBetweenSomePrice = $newDateBetweenSomePrice->first();

            if (strtotime($newStartDate) == strtotime($newDateBetweenSomePrice->start_date)) {
                return collect(["code" => 0])->toJson();
            }

            return collect([
                    "code" => 4,
                    "old_end_date" => Carbon::parse($request->post('start_date'))->subHours(24)->format('d-m-Y'),
                    "end_date" => Carbon::parse($nextPriceToNew->start_date)->subHours(24)->format('d-m-Y'),
                    "id_update_to_price_x" => $newDateBetweenSomePrice->id_deal_price,
                    "id_update_to_price_y" => $nextPriceToNew->id_deal_price
                ])->toJson();
        } else {
            $activePrices = Deal::find($request->post('id_deal'))->prices->where('end_date', '0000-00-00');
            if (!is_null($activePrices)){
                return $this->checkIfTheDateIslowerOrLaterOrEqualToTheRecordInTheTable($newStartDate, $activePrices->first())->toJson();
            }else {
                syslog(LOG_INFO, "No recibimos el valor de activePrice para el id_deal solicitado")
                return collect(["code" => 500])->toJson();
            }
        }

        return collect([ "code" => 5 ])->toJson();
    }

    private function checkIfTheDateIslowerOrLaterOrEqualToTheRecordInTheTable($newStartDate, $price)
    {   
        if ( strtotime($newStartDate) < strtotime($price->start_date) ) {
            return collect([
                    "code" => 2,
                    "new_end_date" => Carbon::parse($price->start_date)->subHours(24)->format('d-m-Y'),
                    "id_update_to_price_x" => $price->id_deal_price
                ]);
        }else if ( strtotime($newStartDate) > strtotime($price->start_date) ) {
            return collect([
                "code" => 3,
                "old_end_date" => Carbon::parse($newStartDate)->subHours(24)->format('d-m-Y'),
                "id_update_to_price_x" => $price->id_deal_price
            ]);
        } else {
            syslog(LOG_INFO, "La fecha es igual a la recogida en la tabla")
            return collect(["code" => 0]);
        }
    }
}
