<?php

namespace App\Command;

use App\BookCreator;
use App\GeneratorSelector;
use App\Util\Api;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use ZipArchive;

class CheckCommand extends Command {

	protected static $defaultName = 'app:check';

	/** @var Api */
	private $api;

	/** @var GeneratorSelector */
	private $generatorSelector;

	public function __construct( Api $api, GeneratorSelector $generatorSelector ) {
		parent::__construct();
		$this->api = $api;
		$this->generatorSelector = $generatorSelector;
	}

	protected function configure() {
		$this->setDescription( 'Run epubcheck on books.' )
			->addOption( 'lang', 'l', InputOption::VALUE_REQUIRED, 'Wikisource language code.', 'en' )
			->addOption( 'nocache', null, InputOption::VALUE_NONE, 'Do not cache anything (re-fetch all data).' )
			->addOption( 'title', 't', InputOption::VALUE_REQUIRED, 'Wiki page name of the work to export' )
			->addOption( 'count', 'c', InputOption::VALUE_REQUIRED, 'How many random pages to check', 10 )
			->addOption( 'namespaces', 's', InputOption::VALUE_REQUIRED, 'Pipe-delimited namespace IDs' );
	}

	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$io = new SymfonyStyle( $input, $output );

		$this->api->setLang( $input->getOption( 'lang' ) );

		if ( $input->getOption( 'nocache' ) ) {
			$this->api->disableCache();
		}

		// Turn off the annoying HTTP logging output unless -v or -vv is used.
		if ( !in_array( $output->getVerbosity(), [ OutputInterface::VERBOSITY_VERBOSE, OutputInterface::VERBOSITY_VERY_VERBOSE ] ) ) {
			$this->api->disableLogging();
		}

		// Full list of pages to check.
		$pages = [];

		// Get title option if it's provided.
		$title = $input->getOption( 'title' );
		if ( $title ) {
			$pages[] = $title;
		}

		// Otherwise, get some random pages.
		if ( !$pages ) {
			// Find content namespaces.
			$namespaces = $input->getOption( 'namespaces' );
			if ( $namespaces === null ) {
				$response = $this->api->queryAsync( [ 'meta' => 'siteinfo', 'siprop' => 'namespaces|namespacealiases' ] )->wait();
				$contentNamespaces = [ 0 ];
				foreach ( $response['query']['namespaces'] ?? [] as $nsInfo ) {
					if ( isset( $nsInfo['content'] ) ) {
						$contentNamespaces[] = $nsInfo['id'];
					}
				}
				$namespaces = implode( '|', $contentNamespaces );
			}
			$randomPages = $this->api->queryAsync( [
				'list' => 'random',
				'rnnamespace' => $namespaces,
				'rnlimit' => $input->getOption( 'count' ),
			] )->wait();
			foreach ( $randomPages['query']['random'] ?? [] as $pageInfo ) {
				$pages[] = $pageInfo['title'];
			}
		}

		// Export these books.
		foreach ( $pages as $page ) {
			$io->section( 'https://' . $this->api->getDomainName() . '/wiki/' . str_replace( ' ', '_', $page ) );
			$creator = BookCreator::forApi( $this->api, 'epub-3', [ 'credits' => false ], $this->generatorSelector );
			$creator->create( $page );
			$jsonOutput = $creator->getFilePath() . '_epubcheck.json';
			$process = new Process( [ 'epubcheck', $creator->getFilePath(), '--json', $jsonOutput ] );
			$process->run();
			$errors = json_decode( file_get_contents( $jsonOutput ), true );
			$hasErrors = false;
			foreach ( $errors['messages'] as $message ) {
				if ( $message['severity'] === 'ERROR' ) {
					$hasErrors = true;
					$lineNum = $message['locations'][0]['line'];
					$colNum = $message['locations'][0]['column'];
					$io->warning(
						"Line $lineNum column $colNum"
						. ' of ' . $message['locations'][0]['path'] . ': '
						. $message['message']
					);
					$zip = new ZipArchive();
					$zip->open( $creator->getFilePath() );
					$fileContents = $zip->getFromName( $message['locations'][0]['path'] );
					$lines = explode( "\n", $fileContents );
					$contextLines = array_slice( $lines, $lineNum - 2, 3, true );
					foreach ( $contextLines as $l => $line ) {
						if ( $l + 1 === $lineNum ) {
							$line = substr( $line, 0, $colNum )
								. '<error>' . substr( $line, $colNum, 1 ) . '</error>'
								. substr( $line, $colNum + 1 );
						}
						$io->writeln( "<info>" . ( $l + 1 ) . ":</info> $line" );
					}
				}
			}
			if ( !$hasErrors ) {
				$io->success( 'No errors found in ' . $page . ' (however, there may be warnings etc.)' );
			}
			if ( file_exists( $jsonOutput ) ) {
				unlink( $jsonOutput );
			}
			if ( file_exists( $creator->getFilePath() ) ) {
				unlink( $creator->getFilePath() );
			}
		}

		return Command::SUCCESS;
	}
}
