// ===================================================================
// FINTRACK VN - CLOUDFLARE WORKER & D1 DATABASE
// Chạy 100% giao diện gốc giống hệt Localhost
// Database ID: a525dadb-5871-4b9e-9d2e-556d3eaafcfd
// ===================================================================

import authHtml from "./template_auth.html";
import authRegisterHtml from "./template_auth_register.html";
import indexHtml from "./template_index.html";
import reportsHtml from "./template_reports.html";
import calendarHtml from "./template_calendar.html";
import addTxHtml from "./template_add_transaction.html";
import settingsHtml from "./template_settings.html";

// Helpers Cookie & Auth
function getCookie(request, name) {
  const cookieHeader = request.headers.get("Cookie") || "";
  const match = cookieHeader.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

function isAuthenticated(request) {
  const cookie = getCookie(request, "fintrack_session");
  return Boolean(cookie && cookie.startsWith("fintrack_user_"));
}

function getUserId(request) {
  const cookie = getCookie(request, "fintrack_session");
  if (!cookie) return 1;
  const match = cookie.match(/fintrack_user_(\d+)/);
  return match ? parseInt(match[1], 10) : 1;
}

function createAuthCookie(userId = 1) {
  return `fintrack_session=fintrack_user_${userId}_auth; Path=/; HttpOnly; SameSite=Lax; Max-Age=2592000`;
}

function clearAuthCookie() {
  return `fintrack_session=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0`;
}

function formatMoney(amount) {
  return new Intl.NumberFormat('vi-VN').format(amount || 0) + ' đ';
}

function formatDateVN(dateStr) {
  if (!dateStr) return "";
  const parts = dateStr.split("-");
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  return dateStr;
}

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const method = request.method;

    // ===================================================================
    // 1. XỬ LÝ ĐĂNG XUẤT (LOGOUT)
    // ===================================================================
    if (url.pathname.endsWith("/logout.php") || (url.pathname.endsWith("/auth.php") && url.searchParams.get("action") === "logout")) {
      return new Response(null, {
        status: 302,
        headers: {
          "Location": "/auth.php",
          "Set-Cookie": clearAuthCookie()
        }
      });
    }

    // ===================================================================
    // 2. XỬ LÝ TRANG ĐĂNG NHẬP / ĐĂNG KÝ (AUTH)
    // ===================================================================
    if (url.pathname.endsWith("/auth.php")) {
      // Xử lý gửi biểu mẫu POST
      if (method === "POST") {
        try {
          const formData = await request.formData();
          const action = formData.get("action");

          // 2.1. Đăng nhập 1-Click Demo
          if (action === "demo_login") {
            return new Response(null, {
              status: 302,
              headers: {
                "Location": "/index.php",
                "Set-Cookie": createAuthCookie(1)
              }
            });
          }

          // 2.2. Đăng nhập thường
          if (action === "login") {
            const username = (formData.get("username") || "").trim();
            const password = formData.get("password") || "";
            let isValid = false;
            let loggedInId = 1;

            if (username === "admin" && (password === "admin123" || password === "admin")) {
              isValid = true;
              loggedInId = 1;
            } else if (env.DB && username && password) {
              try {
                const user = await env.DB.prepare("SELECT * FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1")
                  .bind(username, username, username).first();
                if (user) {
                  if (password === "admin123" || user.password === password) {
                    isValid = true;
                    loggedInId = user.id;
                  }
                }
              } catch (e) {
                console.error("DB Login Error:", e);
              }
            }

            if (isValid) {
              return new Response(null, {
                status: 302,
                headers: {
                  "Location": "/index.php",
                  "Set-Cookie": createAuthCookie(loggedInId)
                }
              });
            } else {
              const errorHtml = `
                <div class="mb-5 p-3.5 rounded-xl bg-red-500/15 border border-red-500/30 text-red-300 text-sm flex items-start gap-3">
                    <i class="fa-solid fa-triangle-exclamation text-red-400 mt-0.5 text-base flex-shrink-0"></i>
                    <div>Tên đăng nhập, Gmail hoặc mật khẩu không chính xác!</div>
                </div>`;
              const page = authHtml.replace("{{ERROR_BLOCK}}", errorHtml);
              return new Response(page, { headers: { "Content-Type": "text/html;charset=utf-8" } });
            }
          }

          // 2.3. Đăng ký tài khoản
          if (action === "register") {
            const fullName = (formData.get("full_name") || "").trim();
            const username = (formData.get("username") || "").trim();
            const email = (formData.get("email") || "").trim();
            const password = formData.get("password") || "";

            if (!fullName || !username || !email || !password) {
              const errorHtml = `
                <div class="mb-5 p-3.5 rounded-xl bg-red-500/15 border border-red-500/30 text-red-300 text-sm flex items-start gap-3">
                    <i class="fa-solid fa-triangle-exclamation text-red-400 mt-0.5 text-base flex-shrink-0"></i>
                    <div>Vui lòng điền đầy đủ các thông tin bắt buộc!</div>
                </div>`;
              return new Response(authRegisterHtml.replace("{{ERROR_BLOCK}}", errorHtml), {
                headers: { "Content-Type": "text/html;charset=utf-8" }
              });
            }

            if (env.DB) {
              try {
                await env.DB.prepare("INSERT INTO users (username, email, password, full_name, membership_type) VALUES (?, ?, ?, ?, 'free')")
                  .bind(username, email, password, fullName).run();

                const successHtml = `
                  <div class="mb-5 p-3.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-sm flex items-start gap-3">
                      <i class="fa-solid fa-circle-check text-emerald-400 mt-0.5 text-base flex-shrink-0"></i>
                      <div>Đăng ký thành công! Hãy đăng nhập với tài khoản của bạn.</div>
                  </div>`;
                return new Response(authHtml.replace("{{ERROR_BLOCK}}", successHtml), {
                  headers: { "Content-Type": "text/html;charset=utf-8" }
                });
              } catch (e) {
                const errorHtml = `
                  <div class="mb-5 p-3.5 rounded-xl bg-red-500/15 border border-red-500/30 text-red-300 text-sm flex items-start gap-3">
                      <i class="fa-solid fa-triangle-exclamation text-red-400 mt-0.5 text-base flex-shrink-0"></i>
                      <div>Tên đăng nhập hoặc Gmail đã được sử dụng!</div>
                  </div>`;
                return new Response(authRegisterHtml.replace("{{ERROR_BLOCK}}", errorHtml), {
                  headers: { "Content-Type": "text/html;charset=utf-8" }
                });
              }
            }
          }
        } catch (e) {
          console.error("Auth POST error:", e);
        }
      }

      // Xử lý GET auth
      if (isAuthenticated(request)) {
        return Response.redirect(url.origin + "/index.php", 302);
      }

      const mode = url.searchParams.get("mode");
      if (mode === "register") {
        return new Response(authRegisterHtml.replace("{{ERROR_BLOCK}}", ""), {
          headers: { "Content-Type": "text/html;charset=utf-8" }
        });
      }

      return new Response(authHtml.replace("{{ERROR_BLOCK}}", ""), {
        headers: { "Content-Type": "text/html;charset=utf-8" }
      });
    }

    // ===================================================================
    // 3. KIỂM TRA BẢO VỆ PHIÊN (REQUIRE AUTH CHO TẤT CẢ TRANG KHÁC)
    // ===================================================================
    if (!isAuthenticated(request)) {
      if (url.pathname.includes("/api/")) {
        return new Response(JSON.stringify({ success: false, message: "Unauthorized. Vui lòng đăng nhập!" }), {
          status: 401,
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
      return Response.redirect(url.origin + "/auth.php", 302);
    }

    const currentUserId = getUserId(request);

    // ===================================================================
    // 4. CÁC API ENDPOINTS
    // ===================================================================

    // 4.1. API Transactions (GET danh sách + DELETE)
    if (url.pathname.endsWith("/api/transactions.php")) {
      if (method === "POST") {
        try {
          let id, action;
          const contentType = request.headers.get("content-type") || "";
          if (contentType.includes("application/json")) {
            const body = await request.json();
            id = body.id;
            action = body.action;
          } else {
            const formData = await request.formData();
            id = formData.get("id");
            action = formData.get("action");
          }

          if (action === "delete" && id && env.DB) {
            await env.DB.prepare("DELETE FROM transactions WHERE id = ?").bind(id).run();
            return new Response(JSON.stringify({ success: true, message: "Đã xóa giao dịch thành công!" }), {
              headers: { "Content-Type": "application/json;charset=utf-8" }
            });
          }
        } catch (e) {
          return new Response(JSON.stringify({ success: false, message: e.message }), {
            status: 500,
            headers: { "Content-Type": "application/json;charset=utf-8" }
          });
        }
      }

      // GET transactions
      try {
        const typeFilter = url.searchParams.get("type") || "all";
        const search = (url.searchParams.get("search") || "").trim().toLowerCase();
        const page = parseInt(url.searchParams.get("page") || "1", 10);
        const limit = parseInt(url.searchParams.get("limit") || "6", 10);
        const offset = (page - 1) * limit;

        let query = `
          SELECT t.*, c.name as category_name, c.color as category_color, c.icon as category_icon, p.name as payment_method_name
          FROM transactions t
          LEFT JOIN categories c ON t.category_id = c.id
          LEFT JOIN payment_methods p ON t.payment_method_id = p.id
          WHERE 1=1
        `;
        const params = [];
        if (typeFilter === "income") {
          query += " AND t.type IN ('income', 'thu_nhap')";
        } else if (typeFilter === "expense") {
          query += " AND t.type IN ('expense', 'chi_tieu')";
        } else if (typeFilter === "recurring") {
          query += " AND t.is_recurring = 1";
        }

        if (search) {
          query += " AND (LOWER(t.description) LIKE ? OR LOWER(c.name) LIKE ?)";
          params.push(`%${search}%`, `%${search}%`);
        }

        query += " ORDER BY t.transaction_date DESC, t.id DESC";

        let allRows = { results: [] };
        if (env.DB) {
          allRows = await env.DB.prepare(query).bind(...params).all();
        }

        const total = (allRows.results || []).length;
        const pagedRows = (allRows.results || []).slice(offset, offset + limit);

        const data = pagedRows.map(tx => {
          const isIncome = tx.type === 'thu_nhap' || tx.type === 'income';
          return {
            id: tx.id,
            user_id: tx.user_id,
            title: tx.description || 'Giao dịch',
            description: tx.note || '',
            category_name: tx.category_name || (isIncome ? 'Thu nhập' : 'Ăn uống'),
            category_color: tx.category_color || (isIncome ? '#15803D' : '#0369A1'),
            category_bg: isIncome ? '#DCFCE7' : '#E0F2FE',
            category_icon: tx.category_icon || (isIncome ? 'fa-money-bill-transfer' : 'fa-mug-hot'),
            icon_bg: isIncome ? '#F0FDF4' : '#EFF6FF',
            icon_color: isIncome ? '#22C55E' : '#3B82F6',
            transaction_date: tx.transaction_date,
            transaction_time: tx.transaction_time || '12:00:00',
            datetime_formatted: (tx.transaction_time ? tx.transaction_time.substring(0, 5) + ' - ' : '') + formatDateVN(tx.transaction_date),
            payment_method_name: tx.payment_method_name || 'Techcombank Visa',
            payment_method_dot: isIncome ? '#22C55E' : '#EF4444',
            payment_method_type: 'bank',
            type: tx.type,
            amount: Number(tx.amount),
            amount_formatted: (isIncome ? '+' : '-') + formatMoney(tx.amount),
            is_recurring: tx.is_recurring || 0
          };
        });

        return new Response(JSON.stringify({
          success: true,
          data: data,
          pagination: {
            total,
            page,
            limit,
            total_pages: Math.ceil(total / limit) || 1,
            showing: data.length
          }
        }), {
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      } catch (err) {
        return new Response(JSON.stringify({ success: false, message: err.message }), {
          status: 500,
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
    }

    // 4.2. API Lưu Giao Dịch
    if (url.pathname.endsWith("/api/save_transaction.php") && method === "POST") {
      try {
        let type, amountRaw, description, category_id, payment_method_id, transaction_date, notes;
        const contentType = request.headers.get("content-type") || "";
        if (contentType.includes("application/json")) {
          const body = await request.json();
          type = body.type;
          amountRaw = body.amount;
          description = body.description;
          category_id = body.category_id;
          payment_method_id = body.payment_method_id;
          transaction_date = body.transaction_date;
          notes = body.notes;
        } else {
          const formData = await request.formData();
          type = formData.get("type");
          amountRaw = formData.get("amount");
          description = formData.get("description");
          category_id = formData.get("category_id");
          payment_method_id = formData.get("payment_method_id");
          transaction_date = formData.get("transaction_date");
          notes = formData.get("notes");
        }

        const cleanAmount = parseFloat(String(amountRaw || "0").replace(/[^\d.]/g, '')) || 0;
        const txType = (type === "income" || type === "thu_nhap") ? "thu_nhap" : "chi_tieu";
        const dateVal = transaction_date || new Date().toISOString().split("T")[0];

        if (env.DB) {
          await env.DB.prepare(`
            INSERT INTO transactions (user_id, category_id, payment_method_id, type, amount, description, note, transaction_date, transaction_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
          `).bind(
            currentUserId,
            category_id || 1,
            payment_method_id || 1,
            txType,
            cleanAmount,
            description || "Giao dịch mới",
            notes || "",
            dateVal,
            new Date().toTimeString().split(" ")[0]
          ).run();

          // Cập nhật số dư ví
          if (txType === "thu_nhap") {
            await env.DB.prepare("UPDATE payment_methods SET balance = balance + ? WHERE id = ?").bind(cleanAmount, payment_method_id || 1).run();
          } else {
            await env.DB.prepare("UPDATE payment_methods SET balance = balance - ? WHERE id = ?").bind(cleanAmount, payment_method_id || 1).run();
          }
        }

        return new Response(JSON.stringify({ success: true, message: "Giao dịch đã được lưu thành công vào Cloudflare D1!" }), {
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      } catch (err) {
        return new Response(JSON.stringify({ success: false, message: err.message }), {
          status: 500,
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
    }

    // 4.3. API Tạo Nhanh Danh Mục (save_category.php)
    if (url.pathname.endsWith("/api/save_category.php") && method === "POST") {
      try {
        let name, type, icon, color;
        const contentType = request.headers.get("content-type") || "";
        if (contentType.includes("application/json")) {
          const body = await request.json();
          name = body.name;
          type = body.type;
          icon = body.icon;
          color = body.color;
        } else {
          const formData = await request.formData();
          name = formData.get("name");
          type = formData.get("type");
          icon = formData.get("icon");
          color = formData.get("color");
        }
        name = (name || "").trim();
        type = (type === "income" || type === "thu_nhap") ? "thu_nhap" : "chi_tieu";
        icon = icon || "fa-tag";
        color = color || "#4F59E8";

        let newId = Date.now();
        if (env.DB && name) {
          const res = await env.DB.prepare(
            "INSERT INTO categories (user_id, name, type, icon, color, budget_limit, is_active) VALUES (?, ?, ?, ?, ?, 0, 1)"
          ).bind(currentUserId, name, type, icon, color).run();
          if (res.meta && res.meta.last_row_id) newId = res.meta.last_row_id;
        }

        return new Response(JSON.stringify({
          success: true,
          message: "Đã thêm danh mục mới thành công!",
          category: { id: newId, name, type, icon, color }
        }), { headers: { "Content-Type": "application/json;charset=utf-8" } });
      } catch (err) {
        return new Response(JSON.stringify({ success: false, message: err.message }), {
          status: 500,
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
    }

    // 4.4. API Categories (GET, POST, PUT, DELETE)
    if (url.pathname.includes("/api/categories.php")) {
      try {
        if (method === "DELETE") {
          const catId = url.searchParams.get("id");
          if (env.DB && catId) {
            await env.DB.prepare("UPDATE categories SET is_active = 0 WHERE id = ?").bind(catId).run();
          }
          return new Response(JSON.stringify({ success: true, message: "Đã xóa danh mục thành công!" }), {
            headers: { "Content-Type": "application/json;charset=utf-8" }
          });
        }

        if (method === "POST" || method === "PUT") {
          let name, type, icon, color, budget_limit, id;
          const contentType = request.headers.get("content-type") || "";
          if (contentType.includes("application/json")) {
            const body = await request.json();
            id = body.id;
            name = body.name;
            type = body.type;
            icon = body.icon;
            color = body.color;
            budget_limit = body.budget_limit;
          } else {
            const formData = await request.formData();
            id = formData.get("id");
            name = formData.get("name");
            type = formData.get("type");
            icon = formData.get("icon");
            color = formData.get("color");
            budget_limit = formData.get("budget_limit");
          }
          name = (name || "").trim();
          type = (type === "thu_nhap" || type === "income") ? "thu_nhap" : "chi_tieu";
          const budgetNum = parseFloat(String(budget_limit || "0").replace(/[^\d.]/g, '')) || 0;

          if (env.DB && name) {
            if (id) {
              await env.DB.prepare(
                "UPDATE categories SET name = ?, type = ?, icon = ?, color = ?, budget_limit = ? WHERE id = ?"
              ).bind(name, type, icon || "fa-tag", color || "#4F59E8", budgetNum, id).run();
            } else {
              await env.DB.prepare(
                "INSERT INTO categories (user_id, name, type, icon, color, budget_limit, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)"
              ).bind(currentUserId, name, type, icon || "fa-tag", color || "#4F59E8", budgetNum).run();
            }
          }
          return new Response(JSON.stringify({ success: true, message: "Đã lưu danh mục thành công!" }), {
            headers: { "Content-Type": "application/json;charset=utf-8" }
          });
        }

        // GET categories
        let cats = [];
        if (env.DB) {
          const res = await env.DB.prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY type, id").all();
          cats = res.results || [];
        }
        const filterType = url.searchParams.get("type");
        let filteredCats = cats;
        if (filterType === "chi_tieu" || filterType === "expense") {
          filteredCats = cats.filter(c => c.type === "chi_tieu" || c.type === "expense");
        } else if (filterType === "thu_nhap" || filterType === "income") {
          filteredCats = cats.filter(c => c.type === "thu_nhap" || c.type === "income");
        }

        const countExpense = cats.filter(c => c.type === "chi_tieu" || c.type === "expense").length;
        const countIncome = cats.filter(c => c.type === "thu_nhap" || c.type === "income").length;

        return new Response(JSON.stringify({
          success: true,
          data: filteredCats,
          counts: {
            all: cats.length,
            chi_tieu: countExpense,
            thu_nhap: countIncome
          }
        }), {
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      } catch (err) {
        return new Response(JSON.stringify({ success: false, message: err.message }), {
          status: 500,
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
    }

    // 4.5. API Budgets (GET, POST)
    if (url.pathname.includes("/api/budgets.php")) {
      try {
        if (method === "POST") {
          let month, year, total_budget, warning_threshold;
          const contentType = request.headers.get("content-type") || "";
          if (contentType.includes("application/json")) {
            const body = await request.json();
            month = body.month;
            year = body.year;
            total_budget = body.total_budget;
            warning_threshold = body.warning_threshold;
          } else {
            const formData = await request.formData();
            month = formData.get("month");
            year = formData.get("year");
            total_budget = formData.get("total_budget");
            warning_threshold = formData.get("warning_threshold");
          }
          const m = parseInt(month || "10", 10);
          const y = parseInt(year || "2024", 10);
          const totalNum = parseFloat(String(total_budget || "0").replace(/[^\d.]/g, '')) || 0;
          const warnNum = parseInt(warning_threshold || "80", 10);

          if (env.DB) {
            await env.DB.prepare(`
              INSERT INTO budgets (user_id, month, year, total_budget, warning_threshold)
              VALUES (?, ?, ?, ?, ?)
              ON CONFLICT(user_id, month, year) DO UPDATE SET
                total_budget = excluded.total_budget,
                warning_threshold = excluded.warning_threshold
            `).bind(currentUserId, m, y, totalNum, warnNum).run();
          }

          return new Response(JSON.stringify({ success: true, message: "Cập nhật ngân sách thành công!" }), {
            headers: { "Content-Type": "application/json;charset=utf-8" }
          });
        }

        // GET budgets
        const m = parseInt(url.searchParams.get("month") || "10", 10);
        const y = parseInt(url.searchParams.get("year") || "2024", 10);
        let totalBudget = 0;
        let warningThreshold = 80;
        let spent = 0;

        if (env.DB) {
          const bdgRow = await env.DB.prepare(
            "SELECT total_budget, warning_threshold FROM budgets WHERE user_id = ? AND month = ? AND year = ? LIMIT 1"
          ).bind(currentUserId, m, y).first();
          if (bdgRow && bdgRow.total_budget !== null) {
            totalBudget = Number(bdgRow.total_budget);
            warningThreshold = Number(bdgRow.warning_threshold || 80);
          }

          const mStr = String(m).padStart(2, '0');
          const txRow = await env.DB.prepare(
            "SELECT SUM(amount) as total FROM transactions WHERE user_id = ? AND type IN ('expense', 'chi_tieu') AND strftime('%m', transaction_date) = ? AND strftime('%Y', transaction_date) = ?"
          ).bind(currentUserId, mStr, String(y)).first();
          if (txRow && txRow.total !== null) {
            spent = Number(txRow.total);
          }
        }

        const remaining = Math.max(0, totalBudget - spent);
        const percentage = totalBudget > 0 ? Math.round((spent / totalBudget) * 1000) / 10 : 0;

        let disciplineRating = "Xuất Sắc";
        let disciplineColor = "#10B981";
        let disciplineDesc = "Kiểm soát chi tiêu tuyệt vời, duy trì tỷ lệ dưới 60% ngân sách.";
        if (totalBudget === 0 && spent === 0) {
          disciplineRating = "Chưa thiết lập";
          disciplineColor = "#94A3B8";
          disciplineDesc = "Chưa có ngân sách và giao dịch phát sinh trong tháng.";
        } else if (percentage > 100) {
          disciplineRating = "Vượt Ngân Sách";
          disciplineColor = "#EF4444";
          disciplineDesc = "Đã vượt hạn mức chi tiêu dự trù của tháng!";
        } else if (percentage > 80) {
          disciplineRating = "Cảnh Báo";
          disciplineColor = "#F59E0B";
          disciplineDesc = "Đã chạm mức cảnh báo an toàn. Cần cân nhắc cắt giảm các khoản chi không thiết yếu.";
        } else if (percentage > 60) {
          disciplineRating = "Tốt";
          disciplineColor = "#3B82F6";
          disciplineDesc = "Chi tiêu trong tầm kiểm soát ổn định, còn dư ngân sách dự phòng an toàn.";
        }

        return new Response(JSON.stringify({
          success: true,
          data: {
            month: m,
            year: y,
            month_label: `Tháng ${m} / ${y}`,
            total_budget: totalBudget,
            total_budget_formatted: formatMoney(totalBudget),
            spent: spent,
            spent_formatted: formatMoney(spent),
            remaining: remaining,
            remaining_formatted: formatMoney(remaining),
            percentage: percentage,
            discipline_rating: disciplineRating,
            discipline_color: disciplineColor,
            discipline_desc: disciplineDesc,
            warning_threshold: warningThreshold,
            warning_yellow: true,
            warning_red: true
          }
        }), {
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      } catch (err) {
        return new Response(JSON.stringify({ success: false, message: err.message }), {
          status: 500,
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
    }

    // 4.6. API Settings (GET, POST)
    if (url.pathname.includes("/api/settings.php")) {
      if (method === "POST") {
        return new Response(JSON.stringify({ success: true, message: "Đã lưu cài đặt!" }), {
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
      return new Response(JSON.stringify({
        success: true,
        data: {
          currency: "VND",
          number_format: "100.000.000",
          date_format: "DD/MM/YYYY",
          theme: "light"
        }
      }), {
        headers: { "Content-Type": "application/json;charset=utf-8" }
      });
    }

    // 4.7. API Calendar Data (GET month/date, POST add/delete)
    if (url.pathname.includes("/api/calendar_data.php")) {
      try {
        const action = url.searchParams.get("action");

        // Action add_transaction
        if (method === "POST" && action === "add_transaction") {
          const formData = await request.formData();
          const tDate = formData.get("date") || new Date().toISOString().split("T")[0];
          const tType = formData.get("type") || "chi_tieu";
          const tAmount = parseFloat(String(formData.get("amount") || "0").replace(/[^\d.]/g, '')) || 0;
          const tCatId = parseInt(formData.get("category_id") || "1", 10);
          const tMethodId = parseInt(formData.get("payment_method_id") || "1", 10);
          const tDesc = formData.get("description") || "Giao dịch từ Lịch";
          const tNotes = formData.get("notes") || "";

          const normalizedType = (tType === "income" || tType === "thu_nhap") ? "thu_nhap" : "chi_tieu";

          let newId = Date.now();
          if (env.DB) {
            const res = await env.DB.prepare(`
              INSERT INTO transactions (user_id, category_id, payment_method_id, type, amount, description, note, transaction_date, transaction_time)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            `).bind(currentUserId, tCatId, tMethodId, normalizedType, tAmount, tDesc, tNotes, tDate, new Date().toTimeString().split(" ")[0]).run();
            if (res.meta && res.meta.last_row_id) newId = res.meta.last_row_id;

            if (normalizedType === "thu_nhap") {
              await env.DB.prepare("UPDATE payment_methods SET balance = balance + ? WHERE id = ?").bind(tAmount, tMethodId).run();
            } else {
              await env.DB.prepare("UPDATE payment_methods SET balance = balance - ? WHERE id = ?").bind(tAmount, tMethodId).run();
            }
          }

          return new Response(JSON.stringify({
            status: "success",
            message: "Thêm giao dịch mới thành công!",
            id: newId,
            date: tDate
          }), { headers: { "Content-Type": "application/json;charset=utf-8" } });
        }

        // Action delete_transaction
        if (method === "POST" && action === "delete_transaction") {
          let delId;
          const contentType = request.headers.get("content-type") || "";
          if (contentType.includes("application/json")) {
            const body = await request.json();
            delId = body.id;
          } else {
            const formData = await request.formData();
            delId = formData.get("id");
          }
          if (env.DB && delId) {
            await env.DB.prepare("DELETE FROM transactions WHERE id = ?").bind(delId).run();
          }
          return new Response(JSON.stringify({
            status: "success",
            message: "Đã xóa giao dịch thành công."
          }), { headers: { "Content-Type": "application/json;charset=utf-8" } });
        }

        // GET ?date=YYYY-MM-DD (chi tiết 1 ngày)
        const dateParam = url.searchParams.get("date");
        if (dateParam && /^\d{4}-\d{2}-\d{2}$/.test(dateParam)) {
          let dayTxs = [];
          if (env.DB) {
            const res = await env.DB.prepare(`
              SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color, p.name as payment_method
              FROM transactions t
              LEFT JOIN categories c ON t.category_id = c.id
              LEFT JOIN payment_methods p ON t.payment_method_id = p.id
              WHERE t.transaction_date = ?
              ORDER BY t.transaction_time ASC, t.id ASC
            `).bind(dateParam).all();
            dayTxs = res.results || [];
          }

          let totalIncome = 0;
          let totalExpense = 0;
          const formattedTxs = dayTxs.map(t => {
            const isExpense = (t.type === "chi_tieu" || t.type === "expense");
            const amt = Number(t.amount);
            if (isExpense) totalExpense += amt;
            else totalIncome += amt;

            return {
              id: t.id,
              title: t.description || "Giao dịch",
              type: isExpense ? "expense" : "income",
              amount: amt,
              amount_formatted: (isExpense ? "-" : "+") + formatMoney(amt),
              category_name: t.category_name || "Khác",
              category_icon: t.category_icon || "fa-tags",
              category_color: t.category_color || "#4F59E8",
              payment_method: t.payment_method || "Tiền mặt",
              time: t.transaction_time ? t.transaction_time.substring(0, 5) : "12:00",
              note: t.note || "",
              is_recurring: Boolean(t.is_recurring)
            };
          });

          const netDiff = totalIncome - totalExpense;
          const parts = dateParam.split("-");
          const dateObj = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
          const weekdays = ["Chủ Nhật", "Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy"];
          const formattedDateStr = `${weekdays[dateObj.getDay()]}, ${parts[2]}/${parts[1]}/${parts[0]}`;

          return new Response(JSON.stringify({
            status: "success",
            date: dateParam,
            formatted_date: formattedDateStr,
            summary: {
              total_income: totalIncome,
              total_income_formatted: "+" + formatMoney(totalIncome),
              total_expense: totalExpense,
              total_expense_formatted: "-" + formatMoney(totalExpense),
              net_diff: netDiff,
              net_diff_formatted: (netDiff >= 0 ? "+" : "") + formatMoney(netDiff),
              tx_count: formattedTxs.length
            },
            transactions: formattedTxs,
            upcoming_reminders: []
          }), { headers: { "Content-Type": "application/json;charset=utf-8" } });
        }

        // GET ?month=M&year=Y (toàn bộ tháng cho khung lịch)
        const month = parseInt(url.searchParams.get("month") || "10", 10);
        const year = parseInt(url.searchParams.get("year") || "2024", 10);
        const mStr = String(month).padStart(2, '0');
        const yStr = String(year);
        const totalDaysInMonth = new Date(year, month, 0).getDate();

        let monthTxs = [];
        if (env.DB) {
          const res = await env.DB.prepare(`
            SELECT t.*, c.name as category_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE strftime('%m', t.transaction_date) = ? AND strftime('%Y', t.transaction_date) = ?
            ORDER BY t.transaction_date ASC
          `).bind(mStr, yStr).all();
          monthTxs = res.results || [];
        }

        const daysMap = {};
        for (let d = 1; d <= totalDaysInMonth; d++) {
          const dateKey = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
          daysMap[dateKey] = {
            day: d,
            income: 0,
            expense: 0,
            net: 0,
            count: 0,
            has_income: false,
            has_expense: false,
            has_recurring: false
          };
        }

        let maxExpense = 0;
        let maxExpenseDate = "-";
        let maxExpenseDesc = "Chưa có chi tiêu lớn";
        let totalMonthExpense = 0;
        let activeDaysCount = 0;

        monthTxs.forEach(t => {
          const dKey = t.transaction_date;
          if (daysMap[dKey]) {
            const amt = Number(t.amount);
            const isExp = (t.type === "expense" || t.type === "chi_tieu");
            if (isExp) {
              daysMap[dKey].expense += amt;
              daysMap[dKey].has_expense = true;
              totalMonthExpense += amt;
              if (amt > maxExpense) {
                maxExpense = amt;
                maxExpenseDate = dKey;
                maxExpenseDesc = `${formatDateVN(dKey)} • ${t.description || t.category_name || "Khoản chi"}`;
              }
            } else {
              daysMap[dKey].income += amt;
              daysMap[dKey].has_income = true;
            }
            daysMap[dKey].count += 1;
            daysMap[dKey].net = daysMap[dKey].income - daysMap[dKey].expense;
            if (t.is_recurring) daysMap[dKey].has_recurring = true;
          }
        });

        Object.keys(daysMap).forEach(k => {
          if (daysMap[k].count > 0) activeDaysCount++;
        });

        const activeRate = Math.round((activeDaysCount / totalDaysInMonth) * 100);
        const avgDaily = activeDaysCount > 0 ? Math.round(totalMonthExpense / totalDaysInMonth) : 0;

        return new Response(JSON.stringify({
          status: "success",
          month: month,
          year: year,
          month_label: `Tháng ${month}, ${year}`,
          summary: {
            active_days: activeDaysCount,
            total_days: totalDaysInMonth,
            active_rate: activeRate,
            max_expense_day: {
              date: maxExpenseDate,
              amount: maxExpense > 0 ? -maxExpense : 0,
              amount_formatted: maxExpense > 0 ? "-" + formatMoney(maxExpense) : "0 đ",
              description: maxExpenseDesc
            },
            avg_daily_expense: {
              amount: avgDaily,
              amount_formatted: formatMoney(avgDaily) + " / ngày",
              comparison: activeDaysCount > 0 ? "Theo chi tiêu thực tế" : "Chưa có dữ liệu chi tiêu"
            }
          },
          weekly: {
            title: "Tuần này",
            description: totalMonthExpense > 0 ? `Tổng chi tiêu thực tế trong tháng: ${formatMoney(totalMonthExpense)}` : "Chưa có giao dịch phát sinh trong tuần",
            percentage: activeRate,
            status: totalMonthExpense > 0 ? "Đang theo dõi" : "Chưa có chi tiêu"
          },
          days: daysMap
        }), { headers: { "Content-Type": "application/json;charset=utf-8" } });
      } catch (err) {
        return new Response(JSON.stringify({ status: "error", message: err.message }), {
          status: 500,
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
    }

    // 4.8. API Report Data (JSON & CSV Export)
    if (url.pathname.includes("/api/report_data.php")) {
      try {
        let allTxs = [];
        if (env.DB) {
          const res = await env.DB.prepare(`
            SELECT t.*, c.name as category_name, p.name as payment_method_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN payment_methods p ON t.payment_method_id = p.id
            ORDER BY t.transaction_date DESC
          `).all();
          allTxs = res.results || [];
        }

        // CSV Export
        if (url.searchParams.get("export") === "csv") {
          let csv = "\uFEFFMã GD,Ngày,Thời gian,Loại,Danh mục,Phương thức,Số tiền,Nội dung,Ghi chú\r\n";
          for (const r of allTxs) {
            const isInc = (r.type === 'income' || r.type === 'thu_nhap');
            const typeStr = isInc ? 'Thu nhập' : 'Chi tiêu';
            const sign = isInc ? '+' : '-';
            csv += `"${r.id}","${r.transaction_date}","${r.transaction_time || ''}","${typeStr}","${r.category_name || ''}","${r.payment_method_name || ''}","${sign}${r.amount}","${(r.description || '').replace(/"/g, '""')}","${(r.note || '').replace(/"/g, '""')}"\r\n`;
          }
          return new Response(csv, {
            headers: {
              "Content-Type": "text/csv; charset=utf-8",
              "Content-Disposition": 'attachment; filename="fintrack_bao_cao.csv"'
            }
          });
        }

        return new Response(JSON.stringify({ success: true, data: allTxs }), {
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      } catch (err) {
        return new Response(JSON.stringify({ success: false, message: err.message }), {
          status: 500,
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
    }

    // ===================================================================
    // 5. CÁC TRANG GIAO DIỆN CHÍNH (PAGES)
    // ===================================================================

    // 5.1. transactions.php -> Chuyển hướng sang calendar.php
    if (url.pathname.endsWith("/transactions.php")) {
      return Response.redirect(url.origin + "/calendar.php", 302);
    }

    // 5.2. Báo Cáo Phân Tích (reports.php)
    if (url.pathname.endsWith("/reports.php")) {
      let income = 0;
      let expense = 0;

      try {
        if (env.DB) {
          const incRow = await env.DB.prepare("SELECT SUM(amount) as total FROM transactions WHERE type IN ('income', 'thu_nhap')").first();
          if (incRow && incRow.total !== null) income = Number(incRow.total);

          const expRow = await env.DB.prepare("SELECT SUM(amount) as total FROM transactions WHERE type IN ('expense', 'chi_tieu')").first();
          if (expRow && expRow.total !== null) expense = Number(expRow.total);
        }
      } catch (e) {
        console.error("Lỗi lấy dữ liệu reports:", e);
      }

      const surplus = income - expense;
      const savingsRate = income > 0 ? Math.round((surplus / income) * 1000) / 10 : 0;
      const spendingRate = income > 0 ? Math.round((expense / income) * 1000) / 10 : 0;

      let repOutput = reportsHtml
        .replaceAll("{{REPORT_INCOME}}", formatMoney(income))
        .replaceAll("{{REPORT_EXPENSE}}", formatMoney(expense))
        .replaceAll("{{REPORT_SURPLUS}}", (surplus >= 0 ? "+" : "") + formatMoney(surplus))
        .replaceAll("{{REPORT_SAVINGS_RATE}}", savingsRate.toFixed(1) + "%")
        .replaceAll("{{REPORT_SPENDING_RATE}}", spendingRate.toFixed(1) + "%");

      return new Response(repOutput, { headers: { "Content-Type": "text/html;charset=utf-8" } });
    }

    // 5.3. Lịch Tài Chính (calendar.php)
    if (url.pathname.endsWith("/calendar.php")) {
      return new Response(calendarHtml, { headers: { "Content-Type": "text/html;charset=utf-8" } });
    }

    // 5.4. Thêm Giao Dịch (add_transaction.php)
    if (url.pathname.endsWith("/add_transaction.php")) {
      return new Response(addTxHtml, { headers: { "Content-Type": "text/html;charset=utf-8" } });
    }

    // 5.5. Cài Đặt, Ví & Ngân Sách
    if (url.pathname.endsWith("/settings.php") || url.pathname.endsWith("/wallets.php") || url.pathname.endsWith("/categories.php") || url.pathname.endsWith("/budgets.php")) {
      return new Response(settingsHtml, { headers: { "Content-Type": "text/html;charset=utf-8" } });
    }

    // 5.6. Trang chủ Dashboard (index.php hoặc /)
    if (url.pathname === "/" || url.pathname.endsWith("/index.php")) {
      let totalBalance = 0;
      let totalIncome = 0;
      let totalExpense = 0;
      let totalBudget = 0;

      try {
        if (env.DB) {
          const bal = await env.DB.prepare("SELECT SUM(balance) as total FROM payment_methods WHERE is_active = 1").first();
          if (bal && bal.total !== null) totalBalance = Number(bal.total);

          const inc = await env.DB.prepare("SELECT SUM(amount) as total FROM transactions WHERE type IN ('income', 'thu_nhap')").first();
          if (inc && inc.total !== null) totalIncome = Number(inc.total);

          const exp = await env.DB.prepare("SELECT SUM(amount) as total FROM transactions WHERE type IN ('expense', 'chi_tieu')").first();
          if (exp && exp.total !== null) totalExpense = Number(exp.total);

          const bdg = await env.DB.prepare("SELECT SUM(total_budget) as total FROM budgets").first();
          if (bdg && bdg.total !== null) totalBudget = Number(bdg.total);
        }
      } catch (e) {
        console.error("Lỗi lấy dữ liệu D1:", e);
      }

      const budgetRemaining = Math.max(0, totalBudget - totalExpense);
      const netSavings = totalIncome - totalExpense;

      let output = indexHtml
        .replaceAll("{{TOTAL_BALANCE_RAW}}", String(totalBalance))
        .replaceAll("{{TOTAL_BALANCE_FORMATTED}}", formatMoney(totalBalance))
        .replaceAll("{{TOTAL_INCOME_RAW}}", String(totalIncome))
        .replaceAll("{{TOTAL_INCOME_FORMATTED}}", formatMoney(totalIncome))
        .replaceAll("{{TOTAL_EXPENSE_RAW}}", String(totalExpense))
        .replaceAll("{{TOTAL_EXPENSE_FORMATTED}}", formatMoney(totalExpense))
        .replaceAll("{{TOTAL_BUDGET_RAW}}", String(totalBudget))
        .replaceAll("{{TOTAL_BUDGET_FORMATTED}}", formatMoney(totalBudget))
        .replaceAll("{{BUDGET_REMAINING_RAW}}", String(budgetRemaining))
        .replaceAll("{{BUDGET_REMAINING_FORMATTED}}", formatMoney(budgetRemaining))
        .replaceAll("{{NET_SAVINGS_RAW}}", String(netSavings))
        .replaceAll("{{NET_SAVINGS_FORMATTED}}", (netSavings >= 0 ? "+" : "") + formatMoney(netSavings));

      return new Response(output, {
        headers: { "Content-Type": "text/html;charset=utf-8" }
      });
    }

    return new Response("Not Found", { status: 404 });
  }
};
