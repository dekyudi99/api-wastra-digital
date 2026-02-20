<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Ai\OpenAiService;
use App\Models\TenunGuide;
use Throwable;

class GenerateTenunGuideJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $guideId){}

    public function handle(OpenAiService $ai)
    {
        $guide = TenunGuide::find($this->guideId);
        if(!$guide) return;

        $payload = [
            'meta'=>['purpose'=>'tenun_guide'],
            'context'=>[
                'design_name'=>$guide->design_name,
                'motif_width_lungsin'=>$guide->motif_width_lungsin,
                'motif_height_pakan'=>$guide->motif_height_pakan,
                'motif_colors'=>$guide->motif_colors,
                'total_lungsin'=>1200
            ]
        ];

        $result = $ai->tenunGuide($payload);

        $guide->update([
            'ai_result'=>$result,
            'status'=>'done'
        ]);
    }

    public function failed(Throwable $e)
    {
        TenunGuide::where('id',$this->guideId)
            ->update(['status'=>'failed']);
    }
}
