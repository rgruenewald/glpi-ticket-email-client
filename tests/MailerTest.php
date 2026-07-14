<?php
declare(strict_types=1);

namespace Symfony\Component\Mime {
    if (!class_exists(Address::class)) {
        final class Address
        {
            public function __construct(
                public string $address,
                public string $name = '',
            ) {
            }
        }
    }

    if (!class_exists(DataPart::class)) {
        final class DataPart
        {
            public bool $inline = false;
            public string $contentId = '';

            public function __construct(
                public string $path,
                public ?string $name,
                public ?string $mime,
            ) {
            }

            public static function fromPath(string $path, ?string $name = null, ?string $mime = null): self
            {
                return new self($path, $name, $mime);
            }

            public function asInline(): self
            {
                $this->inline = true;
                return $this;
            }

            public function setContentId(string $contentId): self
            {
                $this->contentId = $contentId;
                return $this;
            }
        }
    }
}

namespace {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use Symfony\Component\Mime\Address;

    if (!class_exists('GLPIMailer')) {
        final class GLPIMailer
        {
            public static ?self $lastInstance = null;
            public bool $willSend = true;
            private string $error = 'SMTP unavailable';
            private FakeEmail $email;

            public function __construct()
            {
                self::$lastInstance = $this;
                $this->email = new FakeEmail();
            }

            public function getEmail(): FakeEmail
            {
                return $this->email;
            }

            public function send(): bool
            {
                return $this->willSend;
            }

            public function getError(): string
            {
                return $this->error;
            }
        }

        final class FakeEmail
        {
            /** @var list<array{kind:string,address:Address}> */
            public array $recipients = [];
            /** @var list<object> */
            public array $parts = [];
            public ?Address $from = null;
            public string $subject = '';
            public string $html = '';
            public string $text = '';

            public function from(Address $from): self
            {
                $this->from = $from;
                return $this;
            }

            public function addTo(string $address): self
            {
                $this->recipients[] = ['kind' => 'to', 'address' => new Address($address)];
                return $this;
            }

            public function addCc(string $address): self
            {
                $this->recipients[] = ['kind' => 'cc', 'address' => new Address($address)];
                return $this;
            }

            public function addBcc(string $address): self
            {
                $this->recipients[] = ['kind' => 'bcc', 'address' => new Address($address)];
                return $this;
            }

            public function subject(string $subject): self
            {
                $this->subject = $subject;
                return $this;
            }

            public function html(string $html): self
            {
                $this->html = $html;
                return $this;
            }

            public function text(string $text): self
            {
                $this->text = $text;
                return $this;
            }

            public function attachFromPath(string $path, ?string $name = null, ?string $mime = null): self
            {
                $this->parts[] = (object) compact('path', 'name', 'mime');
                return $this;
            }

            public function addPart(object $part): self
            {
                $this->parts[] = $part;
                return $this;
            }

            public function getHeaders(): FakeHeaders
            {
                return new FakeHeaders();
            }
        }

        final class FakeHeaders
        {
            public function get(string $name): FakeHeader
            {
                return new FakeHeader();
            }
        }

        final class FakeHeader
        {
            public function getBodyAsString(): string
            {
                return '<message@example.test>';
            }
        }
    }

    require_once __DIR__ . '/../inc/mailer.class.php';

    final class MailerTest extends TestCase
    {
        #[Test]
        public function sends_complete_message_through_glpi_mailer(): void
        {
            $result = PluginTicketemailclientMailer::send([
                'from' => 'sender@example.test',
                'from_name' => 'Sender',
                'to' => ['to@example.test'],
                'cc' => ['cc@example.test'],
                'bcc' => ['bcc@example.test'],
                'subject' => 'Subject',
                'body_html' => '<p>HTML</p>',
                'body_text' => 'Text',
                'attachments' => [[
                    'path' => '/tmp/document.pdf',
                    'filename' => 'document.pdf',
                    'mime' => 'application/pdf',
                ]],
                'inline_images' => [[
                    'path' => '/tmp/image.png',
                    'cid' => 'image-1@ticketemailclient',
                    'name' => 'image.png',
                    'mime' => 'image/png',
                ]],
            ]);

            self::assertSame(['status' => 'sent', 'msg_id' => '<message@example.test>', 'error' => null], $result);
            $email = GLPIMailer::$lastInstance?->getEmail();
            self::assertInstanceOf(FakeEmail::class, $email);
            self::assertSame('sender@example.test', $email->from?->address);
            self::assertSame('Sender', $email->from?->name);
            self::assertSame(['to', 'cc', 'bcc'], array_column($email->recipients, 'kind'));
            self::assertSame('<p>HTML</p>', $email->html);
            self::assertSame('Text', $email->text);
            self::assertCount(2, $email->parts);
            self::assertSame('image-1@ticketemailclient', $email->parts[1]->contentId);
        }
    }
}
