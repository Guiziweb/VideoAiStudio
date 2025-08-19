<?php

declare(strict_types=1);

namespace App\Video\Entity;

use App\Entity\Customer\Customer;
use App\Shared\Provider\ShopperVideoGenerationProvider;
use App\Video\Entity\Trait\WorkflowStatusTrait;
use App\Video\Form\Type\VideoGenerationCreateType;
use App\Video\State\VideoGenerationCreateProcessor;
use App\Video\VideoGenerationTransitions;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Metadata\Delete;
use Sylius\Resource\Metadata\Index;

#[AsResource(
    alias: 'app.video_generation',
    section: 'shop',
    templatesDir: 'account/video/generation',
    routePrefix: '/{_locale}/account/videos',
    vars: [
        'header' => 'app.ui.my_video_generations',
        'subheader' => 'app.ui.start_generating_message',
    ],
    operations: [
        new Index(
            provider: ShopperVideoGenerationProvider::class,
            vars: [
                'header' => 'app.ui.my_video_generations',
                'subheader' => 'app.ui.start_generating_message',
            ],
        ),
        new Create(
            path: 'generate',
            formType: VideoGenerationCreateType::class,
            processor: VideoGenerationCreateProcessor::class,
            vars: [
                'header' => 'app.ui.new_generation',
                'subheader' => 'app.ui.video_prompt_placeholder',
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
    use WorkflowStatusTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(type: 'text')]
    private string $prompt;

    #[ORM\OneToOne(targetEntity: OrderItemInterface::class, inversedBy: 'videoGeneration', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, unique: true)]
    private ?OrderItemInterface $orderItem = null;

    #[ORM\Column(type: 'integer')]
    private int $tokenCost;

    #[ORM\Column(type: 'string', length: 50)]
    private string $workflowState = VideoGenerationTransitions::STATE_CREATED;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $externalProvider = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalJobId = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $externalSubmittedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $externalErrorMessage = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $videoStorageUrl = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $externalMetadata = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->tokenCost = 1000; // Coût par défaut
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

    public function getOrderItem(): ?OrderItemInterface
    {
        return $this->orderItem;
    }

    public function setOrderItem(?OrderItemInterface $orderItem): self
    {
        $this->orderItem = $orderItem;

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

    public function getWorkflowState(): string
    {
        return $this->workflowState;
    }

    public function setWorkflowState(string $workflowState): self
    {
        $this->workflowState = $workflowState;

        return $this;
    }

    public function getExternalProvider(): ?string
    {
        return $this->externalProvider;
    }

    public function setExternalProvider(?string $externalProvider): self
    {
        $this->externalProvider = $externalProvider;

        return $this;
    }

    public function getExternalJobId(): ?string
    {
        return $this->externalJobId;
    }

    public function setExternalJobId(?string $externalJobId): self
    {
        $this->externalJobId = $externalJobId;

        return $this;
    }

    public function getExternalSubmittedAt(): ?\DateTime
    {
        return $this->externalSubmittedAt;
    }

    public function setExternalSubmittedAt(?\DateTime $externalSubmittedAt): self
    {
        $this->externalSubmittedAt = $externalSubmittedAt;

        return $this;
    }

    public function getExternalErrorMessage(): ?string
    {
        return $this->externalErrorMessage;
    }

    public function setExternalErrorMessage(?string $externalErrorMessage): self
    {
        $this->externalErrorMessage = $externalErrorMessage;

        return $this;
    }

    public function getVideoStorageUrl(): ?string
    {
        return $this->videoStorageUrl;
    }

    public function setVideoStorageUrl(?string $videoStorageUrl): self
    {
        $this->videoStorageUrl = $videoStorageUrl;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getExternalMetadata(): ?array
    {
        return $this->externalMetadata;
    }

    /**
     * @param array<string, mixed>|null $externalMetadata
     */
    public function setExternalMetadata(?array $externalMetadata): self
    {
        $this->externalMetadata = $externalMetadata;

        return $this;
    }
}
