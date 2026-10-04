<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930135010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_7D3656A45E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fill (id INT AUTO_INCREMENT NOT NULL, execution_id VARCHAR(50) NOT NULL, executed_at DATETIME NOT NULL, side VARCHAR(10) NOT NULL, quantity INT NOT NULL, symbol VARCHAR(20) NOT NULL, price NUMERIC(14, 4) NOT NULL, commission NUMERIC(12, 2) NOT NULL, broker_pnl NUMERIC(14, 2) DEFAULT NULL, account_id INT NOT NULL, INDEX IDX_F5438EB49B6B5FBAECC836F960118335 (account_id, symbol, executed_at), UNIQUE INDEX UNIQ_F5438EB49B6B5FBA57125544 (account_id, execution_id), INDEX IDX_F5438EB49B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE trade (id INT AUTO_INCREMENT NOT NULL, symbol VARCHAR(20) NOT NULL, direction VARCHAR(10) NOT NULL, quantity INT NOT NULL, opened_at DATETIME NOT NULL, closed_at DATETIME NOT NULL, entry_price NUMERIC(14, 4) NOT NULL, exit_price NUMERIC(14, 4) NOT NULL, gross_pnl NUMERIC(14, 2) NOT NULL, commission NUMERIC(12, 2) NOT NULL, account_id INT NOT NULL, INDEX IDX_7E1A43669B6B5FBA5D13417F (account_id, closed_at), INDEX IDX_7E1A43669B6B5FBA (account_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE fill ADD CONSTRAINT FK_F5438EB49B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trade ADD CONSTRAINT FK_7E1A43669B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fill DROP FOREIGN KEY FK_F5438EB49B6B5FBA');
        $this->addSql('ALTER TABLE trade DROP FOREIGN KEY FK_7E1A43669B6B5FBA');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE fill');
        $this->addSql('DROP TABLE trade');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
