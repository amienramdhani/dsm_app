<?php
/**
 * Pintu tunggal semua panggilan dari aplikasi (pengganti google.script.run).
 * Request : POST {"fn":"apiDashboard","args":[token, periode, cakupan]}
 * Response: {"ok":true,"result":...}  atau  {"ok":false,"message":"..."}
 */
declare(strict_types=0);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('memory_limit', '1536M');
set_time_limit(300);

require_once __DIR__ . '/lib/blob.php';
require_once __DIR__ . '/lib/api_main.php';
require_once __DIR__ . '/lib/api_analisa.php';
require_once __DIR__ . '/lib/api_tutup.php';
require_once __DIR__ . '/lib/api_weekly.php';
require_once __DIR__ . '/lib/api_exec.php';
require_once __DIR__ . '/lib/api_import.php';
require_once __DIR__ . '/lib/dealer.php';
require_once __DIR__ . '/lib/bulk.php';
require_once __DIR__ . '/lib/api_dealer_transfer_direct.php';
require_once __DIR__ . '/lib/users_bulk.php';
require_once __DIR__ . '/lib/activity.php';
require_once __DIR__ . '/lib/api_kpi.php';
require_once __DIR__ . '/lib/api_kpikhusus.php';
require_once __DIR__ . '/lib/api_portfolio.php';
require_once __DIR__ . '/lib/tx_effective.php';
require_once __DIR__ . '/lib/api_txcorr.php';
require_once __DIR__ . '/lib/api_commit.php';
require_once __DIR__ . '/lib/keep_seller.php';
require_once __DIR__ . '/lib/owner_snapshot.php';
require_once __DIR__ . '/lib/api_dealertarget.php';
require_once __DIR__ . '/lib/transfer_undo.php';
require_once __DIR__ . '/lib/api_admin2.php';
require_once __DIR__ . '/lib/api_acct.php';
require_once __DIR__ . '/lib/api_health.php';
require_once __DIR__ . '/lib/api_backup.php';
require_once __DIR__ . '/lib/api_contact.php';
require_once __DIR__ . '/lib/api_info.php';
require_once __DIR__ . '/lib/api_menu.php';
require_once __DIR__ . '/lib/api_export2.php';
require_once __DIR__ . '/lib/api_brand.php';
require_once __DIR__ . '/lib/rescache.php';
require_once __DIR__ . '/lib/api_telemarketing.php';
require_once __DIR__ . '/lib/api_push.php';
require_once __DIR__ . '/lib/api_notif.php';
require_once __DIR__ . '/lib/api_ai.php';

/** Fungsi yang boleh dipanggil dari browser (daftar putih). */
const DSM_ALLOWED = ['apiCommitComment','apiCommitBoot','apiCommitSave','apiCommitStatus','apiCommitDelete','apiDealerTargetTemplate','apiDealerTargetStage','apiDealerTargetRejected','apiDealerTargetCommit','apiDealerTargetList','apiDealerTargetLog','apiDealerTransferDirectBoot','apiDealerTransferDirectList','apiDealerTransferDirectStage','apiPortfolioTransferPreview','apiPortfolioExitState','apiPortfolioAssets','apiPortfolioEnsureVacant','apiTransferUndoList','apiTransferUndo','apiOwnerHistoryBoot','apiOwnerHistory','apiOwnerHistoryExport','apiKeepSellerBoot','apiKeepSellerSave','apiKeepSellerRevoke','apiTxCorrBoot','apiTxCorrList','apiTxCorrSearch','apiTxCorrHistory','apiTxCorrSave','apiTxCorrRevoke','apiPortfolioTransferCommit','apiNotifState','apiNotifPreview','apiNotifSend','apiNotifTest','apiNotifHistory','apiNotifCancel','apiNotifOpened','apiNotifRuleSave','apiKpiKhususGet','apiKpiKhususSave','apiKpiKhususDelete','apiKpiKhususCopy','apiKpiKhususCopyTo','apiKpiKhususPreview','apiKpiKhususDealers','apiKpiKhususDealerList','apiKpiKhususRuleSave','apiKpiKhususRuleCopy','apiKpiKhususTargets','apiKpiKhususTargetSave','apiKpiKhususTargetCopy','apiPushState','apiPushSave','apiPushTest','apiTMTeam','apiTMDailyReport','apiKpiValidation','apiKpiSearch','apiKpiExport','apiOrderBoot','apiOrderList','apiTMAdmin','apiTMProfileSave','apiTMAssignmentSave','apiTMKpiSettings','apiTMKpiSave','apiTMKpiCopy','apiTMKpi','apiTMRank','apiTMBusinessSave','apiTMBoot','apiTMActivity','apiTMFollowUpSave','apiTMOrderSave','apiBrandAdmin','apiBrandSave','apiBrandSetActive','apiBrandClassificationSave','apiBrandThresholdSave','apiTransactionExportRaw','apiCompare','apiGrowthType','apiHariLibur','apiExportSummary','apiDOS','apiKPIExportOptions','apiKPIExportData','apiDOSExportOptions','apiDOSExportData','apiBrandExportOptions','apiBrandExportData','apiExportDealerCsv','apiExportDealerExcel','apiExportDealerHistory','apiDealerHistory','apiDealerHistorySummary','apiTransactions','apiTransactionGroups','apiDealerStock','apiDealerDetail','apiTeam','apiAdminBanner','apiInfo','apiUploadInfo','apiSetInfoActive','apiDownloadInfo','apiDealerContact','apiSubmitDealerContact','apiContactRequests','apiReviewDealerContacts','apiSaveDealerContact','apiExportDealerContacts','apiStageContactBulk','apiCommitContactBulk','apiDealerTutupAjukan','apiDealerTutupProses','apiDealerTutupUpload','apiStartBackup','apiBackupSettings','apiHealthCheck',
    'apiAccountMergePreview', 'apiAccountMergeCommit', 'apiAccountDeleteCheck', 'apiAccountDelete',
    'apiHariLiburAdmin', 'apiHariLiburSave', 'apiHariLiburHapus', 'apiRegionMapGet', 'apiRegionMapSave',
    'apiRegionBrandGet', 'apiKpiRgmPanel', 'apiRegionBrandSave', 'apiKpiGet', 'apiKpiSaveRegion', 'apiKpiSaveRgm', 'apiKpiCopy', 'apiTargetGet', 'apiTargetSave', 'apiCoverageMatrix',
    'apiActivity', 'apiCheckpoints', 'apiCheckpointRestore',
    'apiSyncStatus',
    'apiUserTemplate', 'apiStageUsers', 'apiCommitUsers', 'apiUsersRejected', 'apiStatusBulkStage', 'apiStatusBulkCommit', 'apiStatusBulkRejected',
    'apiProductMasterGet', 'apiProductImpact', 'apiProductMasterSave', 'apiDealerBrandSettings', 'apiDealerBrandSetCutover', 'apiDealerBrandList', 'apiDealerBrandSuggestions', 'apiDealerBrandSet', 'apiDealerSearch', 'apiDealerEditDetail', 'apiDealerIndukPeek', 'apiDealerSave', 'apiDealerDelete', 'apiDealerDeleteCheck', 'apiIndukBulkTemplate', 'apiIndukBulkStage', 'apiIndukBulkRejected', 'apiIndukBulkCommit', 'apiUserPinExport', 'apiDealerRegionGet', 'apiDealerRegionLookup', 'apiDealerRegionSave', 'apiDealerUploadStage', 'apiDealerUploadRejected', 'apiDealerUploadCommit', 'apiDealerDownloadAll', 'apiDealerTemplate', 'apiStageDealers', 'apiCommitDealers', 'apiAddDealer', 'apiCustomerBulkExport', 'apiStageCustomerBulk', 'apiCommitCustomerBulk', 'apiTransferDealerTemplate', 'apiStageDealerTransfer', 'apiTransferRejected', 'apiCommitDealerTransfer', 'apiMasterBulkTemplate', 'apiStageMasterBulk', 'apiMasterBulkRejected', 'apiCommitMasterBulk',
    // Login & akun
    'apiLogin', 'apiLogin2fa', 'apiTotpSetup', 'apiTotpEnable', 'apiSecurityPanel', 'apiResetTfa', 'apiUnlockAccount', 'apiLogout', 'apiChangePin', 'apiDemoOptions', 'apiStartDemo', 'apiStopDemo',
    // Perangkat (Paket 11 / #37)
    'apiMyDevices', 'apiRenameDevice', 'apiRemoveDevice', 'apiAdminListDevices', 'apiAdminRemoveDevice',
    // Kerangka & Beranda
    'apiBootstrap', 'apiDashboard', 'apiExec', 'apiV6Boot',
    // KPI & Peringkat
    'apiKpiTree', 'apiPersonKpi', 'apiTeamKpi', 'apiRank', 'apiMyRank', 'apiRankDetail',
    // Analisa
    'apiAnalisa', 'apiEarlyWarning', 'apiPotensi', 'apiWeeklyAnalysis', 'apiTeamComparison', 'apiDealerTutupList',
    // Admin & upload (Paket 3)
    'apiAdmin', 'apiSaveUser', 'apiPeriodLock', 'apiResetPin', 'apiSetActive', 'apiSaveExportAccess', 'apiStageRestore', 'apiCommitRestore',
    'apiStageImport', 'apiPublishImport', 'apiStageSO', 'apiPublishSO', 'apiPublishConfig', 'apiRegionImportTemplate',
    // Fitur versi Apps Script yang tidak dipakai lagi (tombol lama di layar → pesan jelas, bukan error)
    'apiSyncRunNow', 'apiSyncSchedule', 'apiSyncRetry', 'apiSyncFileDownload', 'apiAIOptions', 'apiAIChat',
];

/** Fungsi yang mengubah data (diblok saat Mode Demo). */
const DSM_WRITE_FNS = ['apiCommitComment','apiCommitSave','apiCommitStatus','apiCommitDelete','apiKeepSellerSave','apiKeepSellerRevoke','apiDealerTargetStage','apiDealerTargetCommit','apiDealerTransferDirectStage','apiPortfolioTransferCommit','apiPortfolioEnsureVacant','apiTransferUndo','apiTxCorrSave','apiTxCorrRevoke','apiNotifSend','apiNotifTest','apiNotifCancel','apiNotifRuleSave','apiKpiKhususSave','apiKpiKhususDelete','apiKpiKhususCopy','apiKpiKhususCopyTo','apiKpiKhususRuleSave','apiKpiKhususRuleCopy','apiKpiKhususTargetSave','apiKpiKhususTargetCopy','apiTMKpiCopy','apiBrandThresholdSave','apiTMProfileSave','apiTMAssignmentSave','apiTMKpiSave','apiTMBusinessSave','apiTMFollowUpSave','apiTMOrderSave','apiBrandSave','apiBrandSetActive','apiBrandClassificationSave','apiSubmitDealerContact','apiReviewDealerContacts','apiSaveDealerContact','apiStageContactBulk','apiCommitContactBulk','apiDealerTutupAjukan','apiDealerTutupProses','apiDealerTutupUpload','apiStartBackup','apiBackupSettings','apiUploadInfo','apiSetInfoActive',
    'apiAccountMergeCommit','apiAccountDelete','apiHariLiburSave','apiHariLiburHapus','apiRegionMapSave','apiRegionBrandSave','apiKpiSaveRegion','apiKpiSaveRgm','apiKpiCopy','apiTargetSave','apiCheckpointRestore',
    'apiProductMasterSave','apiDealerBrandSetCutover','apiDealerBrandSet','apiStageUsers','apiCommitUsers','apiStatusBulkStage','apiStatusBulkCommit','apiDealerSave','apiDealerDelete','apiIndukBulkStage','apiIndukBulkCommit','apiUserPinExport','apiDealerRegionSave','apiDealerUploadStage','apiDealerUploadCommit',
    'apiStageDealers','apiCommitDealers','apiAddDealer','apiStageCustomerBulk','apiCommitCustomerBulk','apiStageDealerTransfer','apiCommitDealerTransfer','apiStageMasterBulk','apiCommitMasterBulk',
    'apiResetTfa','apiUnlockAccount','apiAdminRemoveDevice','apiSaveUser','apiPeriodLock','apiResetPin','apiSetActive','apiSaveExportAccess','apiStageRestore','apiCommitRestore','apiStageImport','apiPublishImport','apiStageSO','apiPublishSO','apiPublishConfig'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (extension_loaded('zlib') && !ini_get('zlib.output_compression') && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) ob_start('ob_gzhandler');

function dsm_reply(array $data, ?string $cacheKey = null): void {
    $json = json_encode(js_norm($data), (JS_JSON_FLAGS & ~JSON_PRESERVE_ZERO_FRACTION) | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json !== false && $cacheKey !== null && ($data['ok'] ?? false) === true) dsm_cache_put_($cacheKey, $json);
    echo $json === false ? '{"ok":false,"message":"Gagal menyusun jawaban server."}' : $json;
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') throw new AppError('Gunakan aplikasi DSM untuk mengakses data.');
    if (isset($_POST['__payload'])) {
        // Upload form + file (multipart): susun ulang argumen seperti google.script.run
        $body = json_decode((string) $_POST['__payload'], true);
        if (is_array($body) && is_array($body['args'] ?? null)) {
            foreach ($body['args'] as &$a) {
                if (!is_array($a) || !isset($a['__form'])) continue;
                $form = [];
                foreach ((array) $a['__form'] as $k => $v) {
                    if (is_array($v) && isset($v['__file'])) {
                        $f = $_FILES[$v['__file']] ?? null;
                        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE) throw new AppError('File terlalu besar untuk server. Naikkan upload_max_filesize/post_max_size di PHP.');
                        $form[$k] = ($f && ($f['error'] ?? 4) === UPLOAD_ERR_OK) ? Blob::fromUpload($f) : new Blob('', 'application/octet-stream', '');
                    } else $form[$k] = $v;
                }
                $a = $form;
            }
            unset($a);
        }
    } else {
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/')) throw new AppError('File terlalu besar untuk server (melebihi post_max_size PHP).');
        $body = json_decode((string) file_get_contents('php://input'), true);
    }
    if (!is_array($body) || !isset($body['fn'])) throw new AppError('Permintaan tidak valid.');
    $fn = (string) $body['fn'];
    $args = is_array($body['args'] ?? null) ? array_values($body['args']) : [];
    if (!in_array($fn, DSM_ALLOWED, true) || !function_exists($fn)) throw new AppError('Fitur ini belum tersedia di versi DSM sekarang (' . $fn . ').');
    if (in_array($fn, DSM_WRITE_FNS, true)) {
        $tk = $args[0] ?? null; if (is_array($tk)) $tk = $tk['token'] ?? null;
        if (is_string($tk) && strlen($tk) >= 30 && strlen($tk) <= 200) {
            $ss = session_get_('SESSION_' . hash_($tk));
            if ($ss && !empty($ss['demoNik'])) throw new AppError('Mode Demo hanya-baca. Kembali ke akun Super Admin untuk melakukan perubahan.');
        }
    }
    $rf = new ReflectionFunction($fn);
    while (count($args) < $rf->getNumberOfParameters()) $args[] = null;
    tmBeginRequest_($fn, $args);
    $ckey = dsm_cache_key_($fn, $args);
    if ($ckey !== null && ($hit = dsm_cache_get_($ckey)) !== null) { echo $hit; exit; }
    dsm_reply(['ok' => true, 'result' => $fn(...$args)], $ckey);
} catch (AppError $e) {
    $out = ['ok' => false, 'message' => $e->getMessage()];
    if (property_exists($e, 'data') && $e->data) $out['data'] = $e->data;
    dsm_reply($out);
} catch (Throwable $e) {
    error_log('[dsm] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    dsm_reply(['ok' => false, 'message' => 'Terjadi kesalahan di server: ' . $e->getMessage()]);
}
