<?php

namespace App\Http\Livewire;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;
use Livewire\Component;
use Illuminate\Support\Facades\Log;


class UpdateChecker extends Component
{
    public $hasUpdate = false, $newVersion = '',$currentVersion;

    /**
     * @throws GuzzleException
     */
    public function render()
    {
        try{
            $client = new Client(['base_uri' => Config::get('system.update_server'), 'verify' => false ]);
            $request = $client->get('/updates.json');
            $this->currentVersion = Config::get('system.version');
            if($request->getStatusCode() == 200)
            {
                $bodyJson = json_decode($request->getBody(),true);
                $this->newVersion = $bodyJson['channels'][Config::get('system.channel')]['version'];
                $this->hasUpdate = $this->IsUpdateAvailable($this->currentVersion,$this->newVersion);
            }
        } catch (\Exception)
        {
            Log::error('Error Checking Updates');
        }

        return view('livewire.update-checker');
    }

    private function IsUpdateAvailable(mixed $currentVersion, mixed $latestVersion) : bool
    {
        (int)$currentVersion = str_replace('.','',$currentVersion);
        (int)$latestVersion = str_replace('.','',$latestVersion);
        return $latestVersion > $currentVersion;
    }
}
