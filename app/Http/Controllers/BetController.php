<?php

    namespace App\Http\Controllers;

    use App\Models\BalanceTransaction;
    use App\Models\Bet;
    use App\Models\BetSelection;
    use App\Models\Player;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;

    class BetController extends Controller
    {
        use ValidateRequest;

        protected $globalErrors = [];

        protected $failedRes = [
            'error'      => [],
            'selections' => [],
        ];

        public function bet( Request $request )
        {
            if(! $this->validateRequest( $request )) {
                return response( json_encode( $this->failedRes ), 400 );
            }

            $inputs = $request->all();

            $selectionsOdds = array_column( $inputs[ 'selections' ], 'odds' );

            $selectionsOdds = array_product( $selectionsOdds );

            $winAmount = $selectionsOdds * (float) $inputs[ 'stake_amount' ];

            DB::beginTransaction();

            try {
                Player::firstOrCreate( [ 'id' => $inputs[ 'player_id' ] ] );

                $player = Player::findOrFail($inputs['player_id']);

                if ($this->balanceValidation( $player->balance, $inputs['stake_amount'], $inputs)) {
                    return response( json_encode( $this->failedRes ), 400 );
                }

                $bet = Bet::create( [
                                 'player_id'    => $inputs[ 'player_id' ],
                                 'stake_amount' => $inputs[ 'stake_amount' ],
                             ] );

                foreach ( $inputs[ 'selections' ] as $key => $selection ) {
                    $inputs[ 'selections' ][$key]['bet_id'] = $bet->id;
                    $inputs[ 'selections' ][$key]['selection_id'] = $selection['id'];
                    unset( $inputs[ 'selections' ][$key]['id']);
                }

                BetSelection::insert( $inputs[ 'selections' ] );

                BalanceTransaction::create( [
                                                'player_id'     => $player->id,
                                                'amount'        => $winAmount + (float) $player->balance - $inputs['stake_amount'],
                                                'amount_before' => $player->balance,
                                            ] );

                $player->balance = $winAmount + (float) $player->balance - $inputs['stake_amount'];
                $player->save();

                DB::commit();
            }
            catch ( \Exception $e ) {
                DB::rollback();

                return response( $e->getMessage(), 201 );
            }

            return response( json_encode( [] ), 201 );
        }

        public function balanceValidation( $balance, $winAmount, $inputs )
        {
            if ($balance < $winAmount) {
                $error = [
                    "code"    => 11,
                    "message" => "Insufficient balance",
                ];

                array_push( $this->globalErrors, $error );

                $this->failedRes[ 'error' ] = $this->globalErrors;

                $inputs[ 'selections' ] = $this->selectionArrayForError( $inputs[ 'selections' ] );

                $this->failedRes[ 'selections' ] = $inputs[ 'selections' ];

                return TRUE;
            }

            return FALSE;
        }
    }
