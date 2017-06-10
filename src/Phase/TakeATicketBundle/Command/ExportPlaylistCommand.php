<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 11/02/2017
 * Time: 20:20
 */

namespace Phase\TakeATicketBundle\Command;

use Phase\TakeATicket\PlaylistExporter;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportPlaylistCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('ticket:export-playlist')
            // the short description shown while running "php bin/console list"
            ->setDescription('Export playlist')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("Export playlist to CSV file")
            ->addArgument('file', InputArgument::REQUIRED, 'Name of the CSV file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('file');
        $exporter = new PlaylistExporter($this->getContainer()->get('database_connection'));
        $exporter->exportToFile($file);
        $output->writeln("Wrote playlist to $file");
    }
}
