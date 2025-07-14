<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250714160251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE liste DROP CONSTRAINT FK_FCF22AF4D840794C');
        $this->addSql('ALTER TABLE liste ADD CONSTRAINT FK_FCF22AF4D840794C FOREIGN KEY (usser_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE liste DROP CONSTRAINT fk_fcf22af4d840794c');
        $this->addSql('ALTER TABLE liste ADD CONSTRAINT fk_fcf22af4d840794c FOREIGN KEY (usser_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
