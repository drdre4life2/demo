<?php

namespace App\Command;

use App\Entity\Book;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

#[AsCommand(name: 'app:export-books', description: 'Exports books data to JSON')]
class ExportBooksCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dir', null, InputOption::VALUE_OPTIONAL, 'Export directory', 'var/export');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = rtrim($input->getOption('dir'), '/');
        $path = "$dir/books.json";

        $filesystem = new Filesystem();
        try {
            $filesystem->mkdir($dir);
        } catch (IOExceptionInterface $exception) {
            $output->writeln("<error>Could not create directory: $dir</error>");
            return Command::FAILURE;
        }

        $books = $this->em->getRepository(Book::class)->findAll();
        $data = [];

        foreach ($books as $book) {
            $categories = array_map(fn($c) => $c->getName(), $book->getCategories()->toArray());
            $reviews = $book->getReviews();
            $bookmarks = $book->getBookmarks();

            $reviewUsers = [];
            foreach ($reviews as $r) {
                $reviewUsers[$r->getUser()->getId()] = $r->getUser()->email;
            }

            $bookmarkUsers = [];
            foreach ($bookmarks as $b) {
                $bookmarkUsers[$b->getUser()->getId()] = $b->getUser()->email;
            }

            $activeUsers = array_values(array_intersect_assoc($reviewUsers, $bookmarkUsers));

            $data[] = [
                'id' => $book->getId(),
                'author' => $book->getAuthor(),
                'title' => $book->getTitle(),
                'categories' => $categories,
                'reviews' => count($reviews),
                'bookmarks' => count($bookmarks),
                'activeUsers' => $activeUsers,
            ];
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        $output->writeln("<info>Books exported to $path</info>");

        return Command::SUCCESS;
    }
}
