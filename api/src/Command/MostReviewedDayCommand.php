<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\DBAL\Connection;


#[AsCommand(
    name: 'app:most-reviewed-day',
    description: 'Displays the day or month with the highest number of published reviews.'
)]
class MostReviewedDayCommand extends Command
{
  

    private Connection $conn;

    public function __construct(Connection $conn)
    {
        parent::__construct();

        $this->conn = $conn;
    }

    protected function configure(): void
{
    $this->setDescription('Displays the day or month with the most published reviews.')
         ->addOption(
             'period', 
             null,
             InputOption::VALUE_OPTIONAL,
             'Specify "day" to get the day with the most reviews, or "month" to get the month with the most reviews.',
             'day'
         );
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
    $period = $input->getOption('period');
    
    $dateFormat = ($period === 'month') ? 'YYYY-MM' : 'YYYY-MM-DD';
    $query = "
        SELECT TO_CHAR(published_at, :dateFormat) AS period, COUNT(*) 
        FROM review
        GROUP BY period
        ORDER BY COUNT(*) DESC, period DESC
        LIMIT 1
    ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':dateFormat', $dateFormat);
    $result = $stmt->executeQuery()->fetchAssociative();

    if ($result) {
        $output->writeln(
            sprintf(
                'The %s with the most reviews published is: %s, with %d reviews.',
                ucfirst($period),
                $result['period'],
                $result['count']
            )
        );

        return Command::SUCCESS;
    } else {
        $output->writeln('No reviews found.');
        return Command::FAILURE;
    }
}
}
