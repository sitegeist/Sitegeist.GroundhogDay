<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20250424173345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on "mysql".');

        $this->addSql('CREATE TABLE sitegeist_groundhogday_domain_event_occurrence (calendar_id VARCHAR(64) NOT NULL, event_id VARCHAR(64) NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, start_date_utc DATETIME NOT NULL, end_date_utc DATETIME NOT NULL, INDEX by_dates (calendar_id, start_date, end_date), INDEX by_utc_dates (calendar_id, start_date_utc, end_date_utc), PRIMARY KEY(event_id, start_date)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on "mysql".');

        $this->addSql('DROP TABLE sitegeist_groundhogday_domain_event_occurrence');
    }
}
