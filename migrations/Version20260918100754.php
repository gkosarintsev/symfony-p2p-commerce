<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260918100754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accounts (id UUID NOT NULL, account_number VARCHAR(32) NOT NULL, balance BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, version INT DEFAULT 1 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CAC89EACB1A4D127 ON accounts (account_number)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CAC89EACA76ED395 ON accounts (user_id)');
        $this->addSql('CREATE INDEX idx_accounts_account_number ON accounts (account_number)');
        $this->addSql('CREATE TABLE idempotency_records (id UUID NOT NULL, key VARCHAR(128) NOT NULL, request_hash VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL, response_code INT DEFAULT NULL, response_body TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CBC8586C8A90ABA9 ON idempotency_records (key)');
        $this->addSql('CREATE INDEX idx_idempotency_key ON idempotency_records (key)');
        $this->addSql('CREATE TABLE orders (id UUID NOT NULL, amount BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, buyer_id UUID NOT NULL, seller_id UUID NOT NULL, product_id UUID NOT NULL, transaction_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E52FFDEE2FC0CB0F ON orders (transaction_id)');
        $this->addSql('CREATE INDEX IDX_E52FFDEE6C755722 ON orders (buyer_id)');
        $this->addSql('CREATE INDEX IDX_E52FFDEE8DE820D9 ON orders (seller_id)');
        $this->addSql('CREATE INDEX IDX_E52FFDEE4584665A ON orders (product_id)');
        $this->addSql('CREATE TABLE products (id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, price BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(255) NOT NULL, category VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, seller_id UUID NOT NULL, owner_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_products_status ON products (status)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A8DE820D9 ON products (seller_id)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A7E3C61F9 ON products (owner_id)');
        $this->addSql('CREATE TABLE transactions (id UUID NOT NULL, amount BIGINT NOT NULL, currency VARCHAR(3) NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, idempotency_key VARCHAR(128) DEFAULT NULL, reference VARCHAR(255) DEFAULT NULL, source_balance_after BIGINT DEFAULT NULL, destination_balance_after BIGINT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, source_account_id UUID DEFAULT NULL, destination_account_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAA81A4C7FD1C147 ON transactions (idempotency_key)');
        $this->addSql('CREATE INDEX idx_transactions_idempotency_key ON transactions (idempotency_key)');
        $this->addSql('CREATE INDEX idx_transactions_source_created ON transactions (source_account_id, created_at)');
        $this->addSql('CREATE INDEX idx_transactions_dest_created ON transactions (destination_account_id, created_at)');
        $this->addSql('CREATE INDEX IDX_EAA81A4CE7DF2E9E ON transactions (source_account_id)');
        $this->addSql('CREATE INDEX IDX_EAA81A4CC652C408 ON transactions (destination_account_id)');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT FK_CAC89EACA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE6C755722 FOREIGN KEY (buyer_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE8DE820D9 FOREIGN KEY (seller_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE4584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transactions (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A8DE820D9 FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CE7DF2E9E FOREIGN KEY (source_account_id) REFERENCES accounts (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CC652C408 FOREIGN KEY (destination_account_id) REFERENCES accounts (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accounts DROP CONSTRAINT FK_CAC89EACA76ED395');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE6C755722');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE8DE820D9');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE4584665A');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE2FC0CB0F');
        $this->addSql('ALTER TABLE products DROP CONSTRAINT FK_B3BA5A5A8DE820D9');
        $this->addSql('ALTER TABLE products DROP CONSTRAINT FK_B3BA5A5A7E3C61F9');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_EAA81A4CE7DF2E9E');
        $this->addSql('ALTER TABLE transactions DROP CONSTRAINT FK_EAA81A4CC652C408');
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE idempotency_records');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE transactions');
        $this->addSql('DROP TABLE users');
    }
}
