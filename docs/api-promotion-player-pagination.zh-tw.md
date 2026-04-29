# `/api/promotion/player` 分頁支援說明（前端串接文件）

> 版本更新日期：2026-04-23
> 適用端點：`POST /api/promotion/player`
> 目的：原本此 API 一次回傳全部玩家資料，資料量大時容易造成 504 Gateway Timeout，現新增分頁機制降低單次負載。

---

## 1. 變更摘要

| 項目 | 舊行為 | 新行為 |
|---|---|---|
| 未帶分頁參數 | 回傳 `{ success, msg, data: [...] }` 完整陣列 | **維持不變**（向下相容） |
| 帶 `page` 或 `per_page` | 不支援（會被忽略） | 進入**分頁模式**，回傳帶 `pagination` 的物件 |

**重點**：本次為 opt-in 升級，現有前端**不會壞**。前端請**主動改成分頁呼叫**以解決 504 問題。

---

## 2. Request 規格

### Endpoint

```
POST /api/promotion/player
Content-Type: application/json
```

### Body 參數

| 欄位 | 型別 | 必填 | 預設 | 說明 |
|---|---|---|---|---|
| `user_id` | int/string | 否 | - | 使用者 ID，會依權限過濾可見的 server |
| `page` | int | 否 | `1` | 頁碼（從 1 開始）。**帶了就進入分頁模式** |
| `per_page` | int | 否 | `20` | 每頁筆數，**最小 1、最大 100**（超過自動截斷為 100） |

> 只要 `page` 或 `per_page` 任一個有帶，就會進入分頁模式並回傳新的結構。

### Request 範例

```json
{
  "user_id": 12,
  "page": 1,
  "per_page": 20
}
```

---

## 3. Response 規格

### 3.1 分頁模式（新）

HTTP 200，結構：

```jsonc
{
  "data": [
    {
      "id": 101,
      "username": "player_account",
      "server": "ga",
      "character_name": "角色名稱",
      "email": "player@example.com",
      "line_id": "Uxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
      "notify_mail": 1,
      "notify_line": 0,
      "created_at": "2026-04-20 12:34:56",
      "updated_at": "2026-04-21 08:00:00",
      "server_info": {
        "code": "ga",
        "name": "伺服器名稱",
        "cycle": 30
        // ... 其他 server 欄位
      },
      "line": {
        // line 資料（單筆物件或 null）
      },
      "promotion_count": 3,
      "reward_time": "2026-04-15 10:00:00"
    }
    // ... 每頁最多 per_page 筆
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 158,
    "total_pages": 8
  }
}
```

#### 資料排序

依 `player.id DESC`（新到舊），與舊行為的 `array_reverse` 排序結果一致。

#### 無資料時

```json
{
  "data": [],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 0,
    "total_pages": 0
  }
}
```

### 3.2 相容模式（舊，未帶分頁參數）

維持原本回傳：

```jsonc
{
  "success": true,
  "msg": "查詢成功",
  "data": [
    { "id": 101, /* ... */ },
    { "id": 100, /* ... */ }
  ]
}
```

---

## 4. 前端調整建議

### 4.1 判斷回傳結構

為兼顧舊程式，可先做型別判斷：

```ts
const res = await axios.post('/api/promotion/player', payload)
// 分頁模式回傳物件含 pagination；相容模式回傳含 success/data 的舊結構
const isPaginated = 'pagination' in res.data
const list = isPaginated ? res.data.data : res.data.data
const pagination = isPaginated ? res.data.pagination : null
```

### 4.2 推薦預設值

- `per_page`：**20**（列表頁）
- 絕對不要使用 `per_page > 100`，Server 端會截斷為 100

### 4.3 呼叫範例（Vue / axios）

```ts
async function fetchPlayers(page = 1, perPage = 20) {
  const { data } = await axios.post('/api/promotion/player', {
    user_id: userStore.id,
    page,
    per_page: perPage,
  })

  return {
    items: data.data,
    page: data.pagination.page,
    perPage: data.pagination.per_page,
    total: data.pagination.total,
    totalPages: data.pagination.total_pages,
  }
}
```

### 4.4 分頁 UI 提示

- `total_pages === 0` 時顯示「無資料」
- 使用者切換頁碼 → 呼叫同 API 帶新的 `page`
- 切換篩選條件（`user_id`）時務必將 `page` 重置為 `1`

---

## 5. 相容性與遷移時程

| 階段 | 說明 |
|---|---|
| 目前 | 新舊模式並存，未帶參數走舊邏輯 |
| 前端改版後 | 建議全部呼叫點改為分頁模式 |
| 未來（待協調） | 視情況把「舊模式」改為預設回傳第 1 頁、`per_page=20`，屆時會另行公告 |

---

## 6. 常見問題

**Q1. 為什麼不乾脆強制分頁？**
A：避免既有呼叫方（含後台其他頁面、第三方腳本）立即壞掉，改採漸進式遷移。

**Q2. 分頁模式下，`data` 內的欄位會不會不一樣？**
A：**完全相同**。每筆玩家資料仍包含 `server_info`、`line`、`promotion_count`、`reward_time`，只是外層多包一層 `{ data, pagination }`。

**Q3. 排序會變嗎？**
A：不變，仍為 `player.id DESC`（由新到舊），與舊版 `array_reverse` 結果一致。

**Q4. 如果只要「總筆數」不要資料？**
A：可帶 `per_page=1` 取 `pagination.total` 即可。

**Q5. 權限過濾還會生效嗎？**
A：會。`user_id` 仍依原邏輯判斷 server 權限，分頁是在權限過濾**之後**才套用。

**Q6. `per_page` 超過 100 會怎樣？**
A：Server 端自動截斷為 100，不會報錯。

---

## 7. 後端側重點（給維護者參考）

- 修改檔案：[docker/src/app/Controllers/Promotion/Player.php](docker/src/app/Controllers/Promotion/Player.php) 的 `index()`
- 路由不變：[docker/src/app/Config/Routes.php](docker/src/app/Config/Routes.php) 中的 `promotion/player`
- 分頁透過 CodeIgniter Query Builder `limit()` + `countAllResults()` 實作
- `per_page` 上限硬鎖 100，避免被用來打爆 DB
- 使用 `$this->db`（constructor 已初始化的 `promotion` 連線），不另開新連線
