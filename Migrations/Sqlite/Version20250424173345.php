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
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on "sqlite".');

        $this->addSql('CREATE TABLE sitegeist_groundhogday_domain_eventdate (event_id VARCHAR(64) NOT NULL, date DATE, day_of_event SMALLINT, PRIMARY KEY(event_id, date))');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on "sqlite".');

        $this->addSql('DROP TABLE sitegeist_groundhogday_domain_eventdate');
    }
}
