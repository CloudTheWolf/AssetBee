<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Enum\HardwareStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Class HardwareAsset
 *
 * @property int $id
 * @property string $asset_name
 * @property string $product_name
 * @property int $brand_name
 * @property string $serial_number
 * @property int|null $vendor_id
 * @property string|null $custom_tracking_id
 * @property string|null $photo
 * @property string|null $purchase_order
 * @property int $state
 *
 * @package App\Models
 */
class HardwareAsset extends Model
{
	protected $table = 'hardware_asset';
	public $timestamps = false;

	protected $casts = [
		'brand_name' => 'int',
		'vendor_id' => 'int',
		'state' => 'int'
	];

	protected $fillable = [
        'asset_name',
		'product_name',
		'brand_name',
		'serial_number',
		'vendor_id',
		'custom_tracking_id',
		'photo',
		'purchase_order',
		'state'
	];

    public  function whereNotDecommed()
    {
        return $this->where('state','!=',HardwareStatus::DECOMMED);
    }
}
