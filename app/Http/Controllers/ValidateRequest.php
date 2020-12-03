<?php

	namespace App\Http\Controllers;

	use Illuminate\Http\Request;

    trait ValidateRequest
	{
        public function validateRequest( Request $request )
        {
            $inputs = $request->all();
            $isError = FALSE;

            if ( ! $request->has( 'player_id' ) || ! $request->has( 'stake_amount' ) || ! $request->has( 'selections' ) ) {
                $error = [
                    "code"    => 1,
                    "message" => "Betslip structure mismatch",
                ];

                array_push( $this->globalErrors, $error );

                $this->failedRes[ 'error' ] = $this->globalErrors;

                if ( $request->has( 'selections' ) ) {
                    $inputs[ 'selections' ] = $this->selectionArrayForError( $inputs[ 'selections' ] );

                    $this->failedRes[ 'selections' ] = $inputs[ 'selections' ];
                }
                else {
                    unset( $this->failedRes[ 'selections' ] );
                }

                return false;
            }

            if (! is_int( $inputs[ 'player_id' ]) || ! is_string( $inputs[ 'stake_amount' ]) || $inputs[ 'player_id' ] === 0 || ! is_array( $inputs[ 'selections' ]) ) {
                $error = [
                    "code"    => 1,
                    "message" => "Betslip structure mismatch",
                ];

                array_push( $this->globalErrors, $error );

                $this->failedRes[ 'error' ] = $this->globalErrors;

                if ( is_array( $inputs[ 'selections' ]) ) {
                    $inputs[ 'selections' ] = $this->selectionArrayForError( $inputs[ 'selections' ] );

                    $this->failedRes[ 'selections' ] = $inputs[ 'selections' ];
                }

                return false;
            }

            if (count($inputs[ 'selections' ]) === 0) {
                $isError = TRUE;

                $error = [
                    "code"    => 4,
                    "message" => "Minimum number of selections is 1",
                ];

                array_push( $this->globalErrors, $error );
            }

            if (count($inputs[ 'selections' ]) > 20) {
                $isError = TRUE;

                $error = [
                    "code"    => 4,
                    "message" => "Maximum number of selections is 20",
                ];

                array_push( $this->globalErrors, $error );
            }


            if ( (float) $inputs[ 'stake_amount' ] < 0.3 ) {
                $isError = TRUE;

                $error = [
                    "code"    => 2,
                    "message" => "Minimum stake amount is 0.3",
                ];

                array_push( $this->globalErrors, $error );
            }

            if ( (float) ceil( $inputs[ 'stake_amount' ]) > 10000 ) {
                $isError = TRUE;

                $error = [
                    "code"    => 3,
                    "message" => "Maximum stake amount is 10000",
                ];

                array_push( $this->globalErrors, $error );
            }

            $selectionsOdds = array_column( $inputs[ 'selections' ], 'odds' );

            $selectionsOdds = array_product( $selectionsOdds );

            $winAmount = $selectionsOdds * (float) $inputs[ 'stake_amount' ];

            if ( $winAmount > 20000 ) {
                $error = [
                    "code"    => 3,
                    "message" => "Maximum win amount is 20000",
                ];

                array_push( $this->globalErrors, $error );
            }

            $selectionError = $this->selectionArrayForError( $inputs[ 'selections' ] );

            foreach ($selectionError as $error) {
                if (count($error['errors']) > 0) {
                    $isError = TRUE;
                }
            }

            if ($isError) {
                $this->failedRes[ 'error' ] = $this->globalErrors;

                $inputs[ 'selections' ] = $selectionError;

                $this->failedRes[ 'selections' ] = $inputs[ 'selections' ];

                return FALSE;
            }

            return TRUE;
        }

        public function selectionArrayForError( $selections )
        {
            $selectionWithErrors = [];

            $selectionsIDs = array_column( $selections, 'id' );

            foreach ( $selections as $key => $selection ) {
                $selectionWithErrors[ $key ][ 'id' ]     = $selection[ 'id' ];
                $selectionWithErrors[ $key ][ 'errors' ] = [];

                if ( array_count_values( $selectionsIDs )[ $selection[ 'id' ] ] > 1 ) {
                    array_push( $selectionWithErrors[ $key ][ 'errors' ],
                                [
                                    "code"    => 8,
                                    "message" => "Duplicate Selection Found",
                                ] );
                }

                if ( (float) $selection[ 'odds' ] < 1 ) {
                    array_push( $selectionWithErrors[ $key ][ 'errors' ],
                                [
                                    "code"    => 6,
                                    "message" => "Minimum odds are 1",
                                ] );
                }

                if ( (int) ceil( $selection[ 'odds' ]) > 10000 ) {
                    array_push( $selectionWithErrors[ $key ][ 'errors' ],
                                [
                                    "code"    => 6,
                                    "message" => "Maximum odds are 10000",
                                ] );
                }
            }

            return $selectionWithErrors;
        }
	}
