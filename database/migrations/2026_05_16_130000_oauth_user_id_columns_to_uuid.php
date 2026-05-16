<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = $this->getConnection();

        if (DB::connection($connection)->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::connection($connection)->hasTable('oauth_access_tokens')) {
            DB::connection($connection)->statement(
                'ALTER TABLE oauth_access_tokens MODIFY user_id CHAR(36) NULL'
            );
        }

        if (Schema::connection($connection)->hasTable('oauth_auth_codes')) {
            DB::connection($connection)->statement(
                'ALTER TABLE oauth_auth_codes MODIFY user_id CHAR(36) NOT NULL'
            );
        }

        if (Schema::connection($connection)->hasTable('oauth_device_codes')) {
            DB::connection($connection)->statement(
                'ALTER TABLE oauth_device_codes MODIFY user_id CHAR(36) NULL'
            );
        }

        if (Schema::connection($connection)->hasTable('oauth_clients')) {
            DB::connection($connection)->statement(
                'ALTER TABLE oauth_clients MODIFY owner_id CHAR(36) NULL'
            );
        }
    }

    public function down(): void
    {
        $connection = $this->getConnection();

        if (DB::connection($connection)->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::connection($connection)->hasTable('oauth_access_tokens')) {
            DB::connection($connection)->statement(
                'ALTER TABLE oauth_access_tokens MODIFY user_id BIGINT UNSIGNED NULL'
            );
        }

        if (Schema::connection($connection)->hasTable('oauth_auth_codes')) {
            DB::connection($connection)->statement(
                'ALTER TABLE oauth_auth_codes MODIFY user_id BIGINT UNSIGNED NOT NULL'
            );
        }

        if (Schema::connection($connection)->hasTable('oauth_device_codes')) {
            DB::connection($connection)->statement(
                'ALTER TABLE oauth_device_codes MODIFY user_id BIGINT UNSIGNED NULL'
            );
        }

        if (Schema::connection($connection)->hasTable('oauth_clients')) {
            DB::connection($connection)->statement(
                'ALTER TABLE oauth_clients MODIFY owner_id BIGINT UNSIGNED NULL'
            );
        }
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
