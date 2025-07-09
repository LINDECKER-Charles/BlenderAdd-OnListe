<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709181646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE categorie_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE poste_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE topic_id_seq CASCADE');
        $this->addSql('ALTER TABLE topic_categorie DROP CONSTRAINT fk_a0e8428c1f55203d');
        $this->addSql('ALTER TABLE topic_categorie DROP CONSTRAINT fk_a0e8428cbcf5e72d');
        $this->addSql('ALTER TABLE poste DROP CONSTRAINT fk_7c890fab1f55203d');
        $this->addSql('ALTER TABLE poste DROP CONSTRAINT fk_7c890fabd840794c');
        $this->addSql('ALTER TABLE poste_user DROP CONSTRAINT fk_e24c0e76a0905086');
        $this->addSql('ALTER TABLE poste_user DROP CONSTRAINT fk_e24c0e76a76ed395');
        $this->addSql('ALTER TABLE topic DROP CONSTRAINT fk_9d40de1b1e65ccde');
        $this->addSql('ALTER TABLE topic DROP CONSTRAINT fk_9d40de1bd840794c');
        $this->addSql('DROP TABLE topic_categorie');
        $this->addSql('DROP TABLE poste');
        $this->addSql('DROP TABLE poste_user');
        $this->addSql('DROP TABLE categorie');
        $this->addSql('DROP TABLE topic');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE categorie_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE poste_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE topic_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE topic_categorie (topic_id INT NOT NULL, categorie_id INT NOT NULL, PRIMARY KEY(topic_id, categorie_id))');
        $this->addSql('CREATE INDEX idx_a0e8428c1f55203d ON topic_categorie (topic_id)');
        $this->addSql('CREATE INDEX idx_a0e8428cbcf5e72d ON topic_categorie (categorie_id)');
        $this->addSql('CREATE TABLE poste (id SERIAL NOT NULL, usser_id INT DEFAULT NULL, topic_id INT NOT NULL, content TEXT NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_7c890fab1f55203d ON poste (topic_id)');
        $this->addSql('CREATE INDEX idx_7c890fabd840794c ON poste (usser_id)');
        $this->addSql('CREATE TABLE poste_user (poste_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(poste_id, user_id))');
        $this->addSql('CREATE INDEX idx_e24c0e76a0905086 ON poste_user (poste_id)');
        $this->addSql('CREATE INDEX idx_e24c0e76a76ed395 ON poste_user (user_id)');
        $this->addSql('CREATE TABLE categorie (id SERIAL NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE topic (id SERIAL NOT NULL, usser_id INT DEFAULT NULL, topic_liste_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, status BOOLEAN NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_9d40de1b1e65ccde ON topic (topic_liste_id)');
        $this->addSql('CREATE INDEX idx_9d40de1bd840794c ON topic (usser_id)');
        $this->addSql('ALTER TABLE topic_categorie ADD CONSTRAINT fk_a0e8428c1f55203d FOREIGN KEY (topic_id) REFERENCES topic (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE topic_categorie ADD CONSTRAINT fk_a0e8428cbcf5e72d FOREIGN KEY (categorie_id) REFERENCES categorie (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE poste ADD CONSTRAINT fk_7c890fab1f55203d FOREIGN KEY (topic_id) REFERENCES topic (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE poste ADD CONSTRAINT fk_7c890fabd840794c FOREIGN KEY (usser_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE poste_user ADD CONSTRAINT fk_e24c0e76a0905086 FOREIGN KEY (poste_id) REFERENCES poste (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE poste_user ADD CONSTRAINT fk_e24c0e76a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT fk_9d40de1b1e65ccde FOREIGN KEY (topic_liste_id) REFERENCES liste (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT fk_9d40de1bd840794c FOREIGN KEY (usser_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
