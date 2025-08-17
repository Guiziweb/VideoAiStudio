<?php

declare(strict_types=1);

namespace App\Video\Entity;

use App\Entity\Customer\Customer;
use App\Video\Form\Type\VideoGenerationCreateType;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Metadata\Delete;
use Sylius\Resource\Metadata\Index;

#[AsResource(
    alias: 'app.video_generation',
    section: 'shop',
    templatesDir: 'shop/video/generation',
    routePrefix: '',
    vars: [
        'subheader' => 'app.ui.video',
    ],
    operations: [
        new Index(),
        new Create(
            path: 'generate',
            formType: VideoGenerationCreateType::class,
            vars: [
                'subheader' => 'app.ui.video.generate',
            ],
        ),
        new Delete(),
    ],
)]
#[ORM\Entity]
#[ORM\Table(name: 'app_video_generation')]
class VideoGeneration implements ResourceInterface
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(type: 'text')]
    private string $prompt;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $videoUrl = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $paymentId = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $paymentType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $orderId = null;

    #[ORM\Column(type: 'integer')]
    private int $tokenCost;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function setVideoUrl(?string $videoUrl): self
    {
        $this->videoUrl = $videoUrl;

        return $this;
    }

    public function getPaymentId(): ?int
    {
        return $this->paymentId;
    }

    public function setPaymentId(?int $paymentId): self
    {
        $this->paymentId = $paymentId;

        return $this;
    }

    public function getPaymentType(): ?string
    {
        return $this->paymentType;
    }

    public function setPaymentType(?string $paymentType): self
    {
        $this->paymentType = $paymentType;

        return $this;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function setOrderId(?int $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getTokenCost(): int
    {
        return $this->tokenCost;
    }

    public function setTokenCost(int $tokenCost): self
    {
        $this->tokenCost = $tokenCost;

        return $this;
    }
}
