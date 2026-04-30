<?php



namespace App\Database\Migrations;



use CodeIgniter\Database\Migration;



/**

 * api_logs 表新增 perf_data 欄位

 *

 * 用途：存放 AuditProfiler 收集的各段落耗時分解（JSON 格式）。

 * 目前主要供 batchAuditV3 使用，可從 GET api/promotion/logs/detail/:id 查看。

 *

 * JSON 範例：

 * {

 *   "total_ms": 4320,

 *   "steps": [

 *     { "step": "validate_ids",        "elapsed_ms": 210,  "segment_ms": 210  },

 *     { "step": "fetch_standby_items", "elapsed_ms": 430,  "segment_ms": 220  },

 *     { "step": "update_items",        "elapsed_ms": 610,  "segment_ms": 180  },

 *     { "step": "fetch_updated_items", "elapsed_ms": 820,  "segment_ms": 210  },

 *     { "step": "fetch_server_data",   "elapsed_ms": 1010, "segment_ms": 190  },

 *     { "step": "calc_success",        "elapsed_ms": 1015, "segment_ms": 5    },

 *     { "step": "audit_loop",          "elapsed_ms": 2100, "segment_ms": 1085 },

 *     { "step": "fetch_reward_data",   "elapsed_ms": 2280, "segment_ms": 180  },

 *     { "step": "send_rewards_notify", "elapsed_ms": 4320, "segment_ms": 2040 }

 *   ]

 * }

 */

class AddPerfDataToApiLogs extends Migration

{

    public function up()

    {

        $this->db = \Config\Database::connect('promotion');

        $this->forge = \Config\Database::forge('promotion');



        // 防呆：欄位已存在則跳過

        if ($this->db->fieldExists('perf_data', 'api_logs')) {

            return;

        }



        $field = [

            'perf_data' => [

                'type' => 'TEXT',

                'null' => true,

                'after' => 'duration_ms',

            ],

        ];



        $this->forge->addColumn('api_logs', $field);

    }



    public function down()

    {

        $this->db = \Config\Database::connect('promotion');

        $this->forge = \Config\Database::forge('promotion');



        $this->forge->dropColumn('api_logs', 'perf_data');

    }

}

