<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250422114143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE book ADD slug VARCHAR(255) NOT NULL');
    $this->addSql("UPDATE book SET slug = 'book-' || id::text");
    $this->addSql('CREATE UNIQUE INDEX UNIQ_BOOK_SLUG ON book (slug)');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE book DROP slug');
    }
}
