<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration squashée : Ajout de toutes nos fonctionnalités custom
 * - Table app_video_generation
 * - Table wallet  
 * - Modifications ProductVariant (token_amount)
 * - Modifications ProductVariantTranslation (short_description)
 */
final class Version20250820140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add VideoAI Studio custom features: VideoGeneration, Wallet, Product tokens';
    }

    public function up(Schema $schema): void
    {
        // Créer table app_video_generation
        $this->addSql('CREATE TABLE app_video_generation (
            id INT AUTO_INCREMENT NOT NULL, 
            customer_id INT NOT NULL, 
            order_item_id INT DEFAULT NULL, 
            prompt LONGTEXT NOT NULL, 
            token_cost INT NOT NULL, 
            workflow_state VARCHAR(50) NOT NULL, 
            external_provider VARCHAR(50) DEFAULT NULL, 
            external_job_id VARCHAR(255) DEFAULT NULL, 
            external_submitted_at DATETIME DEFAULT NULL, 
            external_error_message LONGTEXT DEFAULT NULL, 
            video_storage_url VARCHAR(500) DEFAULT NULL, 
            external_metadata JSON DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            INDEX IDX_C7BF952C9395C3F3 (customer_id), 
            UNIQUE INDEX UNIQ_C7BF952CE415FB15 (order_item_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Créer table wallet
        $this->addSql('CREATE TABLE wallet (
            id INT AUTO_INCREMENT NOT NULL, 
            customer_id INT NOT NULL, 
            balance INT NOT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            UNIQUE INDEX UNIQ_7C68921F9395C3F3 (customer_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Ajouter les foreign keys
        $this->addSql('ALTER TABLE app_video_generation ADD CONSTRAINT FK_C7BF952C9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id)');
        $this->addSql('ALTER TABLE app_video_generation ADD CONSTRAINT FK_C7BF952CE415FB15 FOREIGN KEY (order_item_id) REFERENCES sylius_order_item (id)');
        $this->addSql('ALTER TABLE wallet ADD CONSTRAINT FK_7C68921F9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id)');
        
        // Modifier tables Sylius existantes
        $this->addSql('ALTER TABLE sylius_product_variant ADD token_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_variant_translation ADD short_description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Supprimer les foreign keys d'abord
        $this->addSql('ALTER TABLE app_video_generation DROP FOREIGN KEY FK_C7BF952C9395C3F3');
        $this->addSql('ALTER TABLE app_video_generation DROP FOREIGN KEY FK_C7BF952CE415FB15');
        $this->addSql('ALTER TABLE wallet DROP FOREIGN KEY FK_7C68921F9395C3F3');
        
        // Supprimer nos tables
        $this->addSql('DROP TABLE app_video_generation');
        $this->addSql('DROP TABLE wallet');
        
        // Enlever modifications Sylius
        $this->addSql('ALTER TABLE sylius_product_variant DROP token_amount');
        $this->addSql('ALTER TABLE sylius_product_variant_translation DROP short_description');
    }
}