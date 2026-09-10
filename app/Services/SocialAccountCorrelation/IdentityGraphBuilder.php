<?php

namespace App\Services\SocialAccountCorrelation;

class IdentityGraphBuilder
{
    public function build(array $seed, array $profiles): array
    {
        $nodes = [['id' => 'seed', 'label' => $seed['platform'].' @'.$seed['username'], 'platform' => $seed['platform'], 'url' => $seed['url']]];
        $edges = [];
        foreach ($profiles as $index => $profile) {
            $id = 'profile-'.$index;
            $identifier = $profile['username'] ?: (($profile['identifier_type'] ?? null) === 'numeric_id' ? 'ID:'.$profile['identifier'] : $profile['identifier']);
            $nodes[] = ['id' => $id, 'label' => $profile['platform'].' '.$identifier, 'platform' => $profile['platform'], 'identifier' => $profile['identifier'] ?? null, 'url' => $profile['source_url']];
            $edges[] = ['source' => 'seed', 'target' => $id, 'label' => $profile['confidence']];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }
}
