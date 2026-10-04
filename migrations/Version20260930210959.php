<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930210959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Authentification : table des utilisateurs, propriétaire des comptes de trading (nom unique par propriétaire).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('DROP INDEX UNIQ_7D3656A45E237E06 ON account');
        $this->addSql('ALTER TABLE account ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE account ADD CONSTRAINT FK_7D3656A47E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7D3656A47E3C61F95E237E06 ON account (owner_id, name)');
        $this->addSql('CREATE INDEX IDX_7D3656A47E3C61F9 ON account (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE app_user');
        $this->addSql('ALTER TABLE account DROP FOREIGN KEY FK_7D3656A47E3C61F9');
        $this->addSql('DROP INDEX UNIQ_7D3656A47E3C61F95E237E06 ON account');
        $this->addSql('DROP INDEX IDX_7D3656A47E3C61F9 ON account');
        $this->addSql('ALTER TABLE account DROP owner_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7D3656A45E237E06 ON account (name)');
    }
}
