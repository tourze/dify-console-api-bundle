<?php

declare(strict_types=1);

namespace Tourze\DifyConsoleApiBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DifyConsoleApiBundle\Repository\WorkflowAppRepository;

#[ORM\Entity(repositoryClass: WorkflowAppRepository::class)]
class WorkflowApp extends BaseApp implements \Stringable
{
    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'workflow_config', type: Types::JSON, nullable: true, options: ['comment' => '工作流配置'])]
    #[Assert\Type(type: 'array', message: '工作流配置必须是数组格式')]
    private ?array $workflowConfig = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'input_schema', type: Types::JSON, nullable: true, options: ['comment' => '输入模式'])]
    #[Assert\Type(type: 'array', message: '输入架构必须是数组格式')]
    private ?array $inputSchema = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'output_schema', type: Types::JSON, nullable: true, options: ['comment' => '输出模式'])]
    #[Assert\Type(type: 'array', message: '输出架构必须是数组格式')]
    private ?array $outputSchema = null;

    /**
     * @return array<string, mixed>|null
     */
    public function getWorkflowConfig(): ?array
    {
        return $this->workflowConfig;
    }

    /**
     * @param array<string, mixed>|null $workflowConfig
     */
    public function setWorkflowConfig(?array $workflowConfig): void
    {
        $this->workflowConfig = $workflowConfig;
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getInputSchema(): ?array
    {
        return $this->inputSchema;
    }

    /**
     * @param array<string, mixed>|null $inputSchema
     */
    public function setInputSchema(?array $inputSchema): void
    {
        $this->inputSchema = $inputSchema;
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOutputSchema(): ?array
    {
        return $this->outputSchema;
    }

    /**
     * @param array<string, mixed>|null $outputSchema
     */
    public function setOutputSchema(?array $outputSchema): void
    {
        $this->outputSchema = $outputSchema;
        $this->setUpdateTime(new \DateTimeImmutable());
    }

    public function __toString(): string
    {
        return $this->name ?? 'Workflow App #' . ($this->id ?? 'new');
    }
}
