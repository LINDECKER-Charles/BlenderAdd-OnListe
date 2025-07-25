<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250710090425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sous_post ADD commenter_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sous_post ADD CONSTRAINT FK_D48732C1B4D5A9E2 FOREIGN KEY (commenter_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D48732C1B4D5A9E2 ON sous_post (commenter_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE sous_post DROP CONSTRAINT FK_D48732C1B4D5A9E2');
        $this->addSql('DROP INDEX IDX_D48732C1B4D5A9E2');
        $this->addSql('ALTER TABLE sous_post DROP commenter_id');
    }
}
