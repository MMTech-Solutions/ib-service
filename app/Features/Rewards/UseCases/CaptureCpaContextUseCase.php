<?php

declare(strict_types=1);

namespace App\Features\Rewards\UseCases;

use App\Features\Programs\Contracts\Data\V1\ResolveProgramCpaSymbolsQueryData;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramContextPort;
use App\Features\Programs\Contracts\Ports\Input\ResolveProgramCpaSymbolsPort;
use App\Features\Rewards\Contracts\Ports\Input\CaptureCpaContextPort;
use App\Features\Rewards\DTOs\CaptureCpaContextData;
use App\Features\Rewards\DTOs\CaptureCpaContextResultData;
use App\Features\Rewards\Exceptions\CpaCaptureNotApplicableException;
use App\Features\Rewards\Factories\RewardRepositoryFactory;
use App\Features\Rules\Contracts\Data\V1\ResolveCpaRuleContextQueryData;
use App\Features\Rules\Contracts\Ports\Input\ResolveCpaRuleContextPort;
use App\Features\Subscriptions\Contracts\Data\V1\ResolveSubscriptionContextQueryData;
use App\Features\Subscriptions\Contracts\Ports\Input\ResolveSubscriptionContextPort;
use Illuminate\Database\UniqueConstraintViolationException;

final class CaptureCpaContextUseCase implements CaptureCpaContextPort
{
    public function __construct(private readonly RewardRepositoryFactory $repositoryFactory, private readonly ResolveSubscriptionContextPort $subscriptions, private readonly ResolveProgramContextPort $programs, private readonly ResolveProgramCpaSymbolsPort $programCpaSymbols, private readonly ResolveCpaRuleContextPort $rules) {}

    public function execute(CaptureCpaContextData $data): CaptureCpaContextResultData
    {
        $repository = $this->repositoryFactory->make();
        $existing = $repository->findCpaContextId($data);
        if ($existing !== null) {
            return new CaptureCpaContextResultData($existing, false);
        }
        $subscription = $this->subscriptions->resolve(new ResolveSubscriptionContextQueryData(external_user_id: $data->ib_user_id, occurred_at: $data->captured_at));
        if (! $subscription->found()) {
            throw CpaCaptureNotApplicableException::create('subscription_context_absent');
        }
        $context = $subscription->context;
        $rule = $this->rules->resolve(new ResolveCpaRuleContextQueryData($context->program_id, $data->captured_at));
        if (! $rule->found() || $rule->configuration === null) {
            throw CpaCaptureNotApplicableException::create('cpa_rule_assignment_absent');
        }
        $symbols = [];
        foreach ($rule->configuration['volume_modules'] as $conversion) {
            $moduleId = $conversion['module_id'];
            $symbols[$moduleId] = $this->programCpaSymbols->resolve(new ResolveProgramCpaSymbolsQueryData($context->program_id, $moduleId, $data->captured_at));
        }
        try {
            $captured = $repository->captureCpaContext($data, $context, $rule, $symbols, $rule->configuration);

            return new CaptureCpaContextResultData($captured['id'], $captured['created']);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $repository->findCpaContextId($data);
            if ($existing !== null) {
                return new CaptureCpaContextResultData($existing, false);
            }
            throw $exception;
        }
    }
}
