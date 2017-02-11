<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 11/02/2017
 * Time: 20:19
 */

namespace Phase\TakeATicketBundle\Command;

use Phase\TakeATicket\SongLoader;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadSongsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('ticket:load-songs')
            // the short description shown while running "php bin/console list"
            ->setDescription('Load songlist')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("Load songlist from XLS file")
            ->addArgument('file', InputArgument::REQUIRED, 'Name of the CSV file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $loader = new SongLoader();

        $file = $input->getArgument('file');
        $songsLoaded = $loader->run($file, $this->getContainer()->get('database_connection'));
        $output->writeln("Loaded $songsLoaded songs from $file");
    }
}