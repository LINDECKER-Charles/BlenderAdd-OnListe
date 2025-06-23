<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250623124750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE liste_addon (liste_id INT NOT NULL, addon_id INT NOT NULL, PRIMARY KEY(liste_id, addon_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_CE7E45D1E85441D8 ON liste_addon (liste_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_CE7E45D1CC642678 ON liste_addon (addon_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE liste_addon ADD CONSTRAINT FK_CE7E45D1E85441D8 FOREIGN KEY (liste_id) REFERENCES liste (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE liste_addon ADD CONSTRAINT FK_CE7E45D1CC642678 FOREIGN KEY (addon_id) REFERENCES addon (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE liste_addon DROP CONSTRAINT FK_CE7E45D1E85441D8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE liste_addon DROP CONSTRAINT FK_CE7E45D1CC642678
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE liste_addon
        SQL);
    }
}
