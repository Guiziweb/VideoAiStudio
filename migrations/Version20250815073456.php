<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250815073456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wallet (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, balance INT NOT NULL, UNIQUE INDEX UNIQ_7C68921F9395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE wallet_transaction (id INT AUTO_INCREMENT NOT NULL, wallet_id INT NOT NULL, type VARCHAR(10) NOT NULL, amount INT NOT NULL, INDEX IDX_7DAF972712520F3 (wallet_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE wallet ADD CONSTRAINT FK_7C68921F9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id)');
        $this->addSql('ALTER TABLE wallet_transaction ADD CONSTRAINT FK_7DAF972712520F3 FOREIGN KEY (wallet_id) REFERENCES wallet (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wallet DROP FOREIGN KEY FK_7C68921F9395C3F3');
        $this->addSql('ALTER TABLE wallet_transaction DROP FOREIGN KEY FK_7DAF972712520F3');
        $this->addSql('DROP TABLE wallet');
        $this->addSql('DROP TABLE wallet_transaction');
    }
}
