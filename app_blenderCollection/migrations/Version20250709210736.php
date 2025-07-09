<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709210736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categorie (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE poste (id SERIAL NOT NULL, usser_id INT DEFAULT NULL, topic_id INT NOT NULL, content TEXT NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7C890FABD840794C ON poste (usser_id)');
        $this->addSql('CREATE INDEX IDX_7C890FAB1F55203D ON poste (topic_id)');
        $this->addSql('CREATE TABLE poste_user (poste_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(poste_id, user_id))');
        $this->addSql('CREATE INDEX IDX_E24C0E76A0905086 ON poste_user (poste_id)');
        $this->addSql('CREATE INDEX IDX_E24C0E76A76ED395 ON poste_user (user_id)');
        $this->addSql('CREATE TABLE topic (id SERIAL NOT NULL, usser_id INT DEFAULT NULL, topic_liste_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, status BOOLEAN NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9D40DE1BD840794C ON topic (usser_id)');
        $this->addSql('CREATE INDEX IDX_9D40DE1B1E65CCDE ON topic (topic_liste_id)');
        $this->addSql('CREATE TABLE topic_categorie (topic_id INT NOT NULL, categorie_id INT NOT NULL, PRIMARY KEY(topic_id, categorie_id))');
        $this->addSql('CREATE INDEX IDX_A0E8428C1F55203D ON topic_categorie (topic_id)');
        $this->addSql('CREATE INDEX IDX_A0E8428CBCF5E72D ON topic_categorie (categorie_id)');
        $this->addSql('ALTER TABLE poste ADD CONSTRAINT FK_7C890FABD840794C FOREIGN KEY (usser_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE poste ADD CONSTRAINT FK_7C890FAB1F55203D FOREIGN KEY (topic_id) REFERENCES topic (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE poste_user ADD CONSTRAINT FK_E24C0E76A0905086 FOREIGN KEY (poste_id) REFERENCES poste (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE poste_user ADD CONSTRAINT FK_E24C0E76A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT FK_9D40DE1BD840794C FOREIGN KEY (usser_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT FK_9D40DE1B1E65CCDE FOREIGN KEY (topic_liste_id) REFERENCES liste (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE topic_categorie ADD CONSTRAINT FK_A0E8428C1F55203D FOREIGN KEY (topic_id) REFERENCES topic (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE topic_categorie ADD CONSTRAINT FK_A0E8428CBCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE liste ADD download INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE poste DROP CONSTRAINT FK_7C890FABD840794C');
        $this->addSql('ALTER TABLE poste DROP CONSTRAINT FK_7C890FAB1F55203D');
        $this->addSql('ALTER TABLE poste_user DROP CONSTRAINT FK_E24C0E76A0905086');
        $this->addSql('ALTER TABLE poste_user DROP CONSTRAINT FK_E24C0E76A76ED395');
        $this->addSql('ALTER TABLE topic DROP CONSTRAINT FK_9D40DE1BD840794C');
        $this->addSql('ALTER TABLE topic DROP CONSTRAINT FK_9D40DE1B1E65CCDE');
        $this->addSql('ALTER TABLE topic_categorie DROP CONSTRAINT FK_A0E8428C1F55203D');
        $this->addSql('ALTER TABLE topic_categorie DROP CONSTRAINT FK_A0E8428CBCF5E72D');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE poste');
        $this->addSql('DROP TABLE poste_user');
        $this->addSql('DROP TABLE topic');
        $this->addSql('DROP TABLE topic_categorie');
        $this->addSql('ALTER TABLE liste DROP download');
    }
}
