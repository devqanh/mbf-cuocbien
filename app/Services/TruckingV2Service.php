<?php

namespace App\Services;

use App\Models\TruckingContType;
use App\Models\TruckingCostItem;
use App\Models\TruckingCostLine;
use App\Models\TruckingChohoItem;
use App\Models\TruckingCustomer;
use App\Models\TruckingDriver;
use App\Models\TruckingLocation;
use App\Models\TruckingPayer;
use App\Models\TruckingPriceRow;
use App\Models\TruckingFuelPrice;
use App\Models\TruckingRevenueItem;
use App\Models\TruckingRouteFee;
use App\Models\TruckingSalaryItem;
use App\Models\TruckingTripCostBatch;
use App\Models\TruckingTripCostLine;
use App\Models\TruckingVehicleCost;
use App\Models\TruckingVehicleDepreciation;
use App\Models\TruckingVehicleUsage;
use App\Models\TruckingSetting;
use App\Models\TruckingAttachment;
use App\Models\TruckingPlanLink;
use App\Models\TruckingShipment;
use App\Models\TruckingShipmentWarehouse;
use App\Models\TruckingVehicleCostType;
use App\Models\TruckingAssetCategory;
use App\Support\Hashid;
use App\Models\TruckingStatement;
use App\Models\TruckingVehicle;
use App\Models\TruckingWarehouse;
use App\Models\User;
use App\Notifications\SpendRequestCreatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Trucking v2 — serialize DB ⇄ shape mà prototype (dev/trucking.html) dùng,
 * và persist (upsert lồng nhau). Mọi số tiền giao tiếp với frontend là chuỗi
 * chữ số (VND, không phần lẻ) khớp helper onlyDigits/groupVND bên client.
 */
class TruckingV2Service
{
    use \App\Services\Trucking\Concerns\FormatsTruckingValues;
    use \App\Services\Trucking\Concerns\HandlesShipments;
    use \App\Services\Trucking\Concerns\HandlesCatalog;
    use \App\Services\Trucking\Concerns\HandlesFleetAssets;
    use \App\Services\Trucking\Concerns\HandlesPlanLinks;
    use \App\Services\Trucking\Concerns\HandlesSpendRequests;
    use \App\Services\Trucking\Concerns\HandlesVehicleDetail;
    use \App\Services\Trucking\Concerns\HandlesTripAndDrivers;
    use \App\Services\Trucking\Concerns\HandlesPricingAndImport;
    use \App\Services\Trucking\Concerns\HandlesStatements;
    use \App\Services\Trucking\Concerns\HandlesStatementPricing;
}
