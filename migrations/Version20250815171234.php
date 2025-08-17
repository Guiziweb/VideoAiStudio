<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250815171234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_video_generation (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, prompt LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, video_url VARCHAR(255) DEFAULT NULL, wallet_transaction_id INT DEFAULT NULL, order_id INT DEFAULT NULL, token_cost INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_C7BF952C9395C3F3 (customer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_video_generation ADD CONSTRAINT FK_C7BF952C9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_video_generation DROP FOREIGN KEY FK_C7BF952C9395C3F3');
        $this->addSql('DROP TABLE app_video_generation');
    }
}
