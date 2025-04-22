<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250422113723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE book ADD promotion_status VARCHAR(255) NOT NULL");
        $this->addSql("
        UPDATE book
        SET promotion_status = CASE
            WHEN is_promoted = false THEN 'None'
            WHEN is_promoted = true THEN 'Basic'
            ELSE 'None'
        END
    ");
        $this->addSql("ALTER TABLE book DROP is_promoted");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql("ALTER TABLE book ADD is_promoted BOOLEAN DEFAULT FALSE NOT NULL");

        // Convert promotionStatus back to isPromoted
        $this->addSql("
        UPDATE book
        SET is_promoted = CASE
            WHEN promotion_status = 'Basic' THEN true
            ELSE false
        END
    ");

        // Remove the promotionStatus column
        $this->addSql("ALTER TABLE book DROP promotion_status");
    }
}
