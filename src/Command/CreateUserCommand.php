<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Throwable;

class CreateUserCommand extends Command
{
    private const HELP_TEXT = <<< 'MARCA_FIN'
    bin/console miw:create-user <useremail> <password> [<roleAdmin>]

    This command allows you to add a new user.
    ej: bin/console miw:create-user "admin1@miw.upm.es" "*MyPa44w0r6*" true

MARCA_FIN;

    /**
     * @var string|null The name of the command (the part after "bin/console")
     */
    protected static $defaultName = 'miw:create-user';

    private const ARG_EMAIL = 'useremail';
    private const ARG_PASSWD = 'password';
    private const ARG_ROLE_ADMIN = 'hasRoleAdmin';

    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates a new user')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp(self::HELP_TEXT)
            ->addArgument(
                self::ARG_EMAIL,
                InputArgument::REQUIRED,
                'User e-mail'
            )
            ->addArgument(
                self::ARG_PASSWD,
                InputArgument::REQUIRED,
                'User password'
            )
            ->addArgument(
                self::ARG_ROLE_ADMIN,
                InputArgument::OPTIONAL,
                'User has role Admin',
                false
            );
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int 0 if everything went fine, or an exit code
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $useremail = strval($input->getArgument(self::ARG_EMAIL));
        if (
            $input->hasArgument(self::ARG_ROLE_ADMIN)
            && strcasecmp('true', strval($input->getArgument(self::ARG_ROLE_ADMIN))) === 0
        ) {
            $roles = [ 'ROLE_ADMIN'];
        } else {
            $roles = [];
        }
        $user = new User(
            $useremail,
            strval($input->getArgument(self::ARG_PASSWD)),
            $roles
        );
        // hash the password (based on the security.yaml config for the $user class)
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            strval($input->getArgument(self::ARG_PASSWD))
        );
        $user->setPassword($hashedPassword);

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (Throwable $exception) {
            $output->writeln([
                'error' => $exception->getCode(),
                'message' => $exception->getMessage()
            ]);

            return intval($exception->getCode());
        }

        // outputs multiple lines to the console (adding "\n" at the end of each line)
        $output->writeln([
            '',
            'User Creator:',
            '=============',
            "  Created user '$useremail' with id: " . $user->getId(),
            ''
        ]);

        return 0;
    }
}
