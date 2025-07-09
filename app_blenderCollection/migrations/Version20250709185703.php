<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709185703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE post (id SERIAL NOT NULL, commentaire_id INT NOT NULL, commenter_id INT DEFAULT NULL, content TEXT NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5A8A6C8DBA9CD190 ON post (commentaire_id)');
        $this->addSql('CREATE INDEX IDX_5A8A6C8DB4D5A9E2 ON post (commenter_id)');
        $this->addSql('CREATE TABLE post_user (post_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(post_id, user_id))');
        $this->addSql('CREATE INDEX IDX_44C6B1424B89032C ON post_user (post_id)');
        $this->addSql('CREATE INDEX IDX_44C6B142A76ED395 ON post_user (user_id)');
        $this->addSql('CREATE TABLE sous_post (id SERIAL NOT NULL, post_id INT NOT NULL, content TEXT NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D48732C14B89032C ON sous_post (post_id)');
        $this->addSql('CREATE TABLE sous_post_user (sous_post_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(sous_post_id, user_id))');
        $this->addSql('CREATE INDEX IDX_5FCF5A0D68807B61 ON sous_post_user (sous_post_id)');
        $this->addSql('CREATE INDEX IDX_5FCF5A0DA76ED395 ON sous_post_user (user_id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DBA9CD190 FOREIGN KEY (commentaire_id) REFERENCES liste (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DB4D5A9E2 FOREIGN KEY (commenter_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE post_user ADD CONSTRAINT FK_44C6B1424B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE post_user ADD CONSTRAINT FK_44C6B142A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sous_post ADD CONSTRAINT FK_D48732C14B89032C FOREIGN KEY (post_id) REFERENCES post (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sous_post_user ADD CONSTRAINT FK_5FCF5A0D68807B61 FOREIGN KEY (sous_post_id) REFERENCES sous_post (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sous_post_user ADD CONSTRAINT FK_5FCF5A0DA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE post DROP CONSTRAINT FK_5A8A6C8DBA9CD190');
        $this->addSql('ALTER TABLE post DROP CONSTRAINT FK_5A8A6C8DB4D5A9E2');
        $this->addSql('ALTER TABLE post_user DROP CONSTRAINT FK_44C6B1424B89032C');
        $this->addSql('ALTER TABLE post_user DROP CONSTRAINT FK_44C6B142A76ED395');
        $this->addSql('ALTER TABLE sous_post DROP CONSTRAINT FK_D48732C14B89032C');
        $this->addSql('ALTER TABLE sous_post_user DROP CONSTRAINT FK_5FCF5A0D68807B61');
        $this->addSql('ALTER TABLE sous_post_user DROP CONSTRAINT FK_5FCF5A0DA76ED395');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE post_user');
        $this->addSql('DROP TABLE sous_post');
        $this->addSql('DROP TABLE sous_post_user');
    }
}
