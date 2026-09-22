<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->text('card_token')->change();

            $table->string('card_token_hash', 64)
                ->nullable()
                ->after('card_token');
        });

        $cards = DB::table('credit_cards')
            ->select('id', 'card_token')
            ->get();

        foreach ($cards as $card) {
            $plainToken = (string) $card->card_token;

            DB::table('credit_cards')
                ->where('id', $card->id)
                ->update([
                    'card_token' => Crypt::encryptString($plainToken),
                    'card_token_hash' => hash_hmac(
                        'sha256',
                        $plainToken,
                        (string) config('app.key')
                    ),
                ]);
        }

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->unique('card_token_hash');
            $table->dropUnique('credit_cards_card_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropUnique('credit_cards_card_token_hash_unique');
        });

        $cards = DB::table('credit_cards')
            ->select('id', 'card_token')
            ->get();

        foreach ($cards as $card) {
            DB::table('credit_cards')
                ->where('id', $card->id)
                ->update([
                    'card_token' => Crypt::decryptString($card->card_token),
                ]);
        }

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->string('card_token')->change();
            $table->dropColumn('card_token_hash');
            $table->unique('card_token');
        });
    }
};
