<?php

declare(strict_types=1);

namespace Chubbyphp\SocketServerMock;

use Chubbyphp\SocketServerMock\Stream\ConnectionInterface;
use Chubbyphp\SocketServerMock\Stream\ServerFactoryInterface;
use Chubbyphp\SocketServerMock\Stream\ServerInterface;

final class SocketServerMock
{
    /**
     * @var ServerFactoryInterface
     */
    private $serverFactory;

    public function __construct(ServerFactoryInterface $serverFactory)
    {
        $this->serverFactory = $serverFactory;
    }

    public function run(string $host, int $port, MessageLogsInterface $messageLogs): void
    {
        $socketServer = $this->serverFactory->createByHostAndPort($host, $port);

        $this->processMessageLogs($socketServer, $messageLogs);
    }

    private function processMessageLogs(ServerInterface $socketServer, MessageLogsInterface $messageLogs): void
    {
        while (null !== $messageLog = $messageLogs->getNextMessageLog()) {
            $socketConnection = $socketServer->createConnection();

            $this->processMessageLog($socketConnection, $messageLog);
        }
    }

    private function processMessageLog(ConnectionInterface $socketConnection, MessageLogInterface $messageLog): void
    {
        while (null !== $message = $messageLog->getNextMessage()) {
            $input = $this->getDecodedValue($message->getInput());
            $availableInputs = [
                $input,
                Message::SIGNAL_SHUTDOWN,
                Message::SIGNAL_SLEEP,
            ];

            $givenInput = '';
            while (true) {
                $givenInput .= $socketConnection->read(1);

                if (!$this->patternMatchesAvailableInputs($availableInputs, $givenInput)) {
                    throw SocketServerMockException::createByInvalidInput($givenInput, $input);
                }

                if ($input === $givenInput) {
                    $socketConnection->write($this->getDecodedValue($message->getOutput()));

                    continue 2;
                } elseif (Message::SIGNAL_SHUTDOWN === $givenInput) {
                    exit();
                } elseif (Message::SIGNAL_SLEEP  === $givenInput) {
                    sleep(2);
                    continue 2;
                }
            }
        }
    }

    private function getDecodedValue(string $value): string
    {
        if (0 === strpos($value, 'base64:')) {
            return base64_decode(substr($value, 7));
        }

        return $value;
    }

    /**
     * @param string[] $availableInputs
     */
    private function patternMatchesAvailableInputs(array $availableInputs, string $givenInput): bool
    {
        foreach ($availableInputs as $item) {
            if (\str_starts_with($item, $givenInput)) {
                return true;
            }
        }
        return false;
    }
}
