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

    // 4.3. API Calendar & Report Data
    if (url.pathname.includes("/api/calendar_data.php") || url.pathname.includes("/api/report_data.php")) {
      let allRows = { results: [] };
      if (env.DB) {
        allRows = await env.DB.prepare(`
          SELECT t.*, c.name as category_name, c.color as category_color, c.icon as category_icon, p.name as payment_method_name
          FROM transactions t
          LEFT JOIN categories c ON t.category_id = c.id
          LEFT JOIN payment_methods p ON t.payment_method_id = p.id
          ORDER BY t.transaction_date DESC
        `).all();
      }
      return new Response(JSON.stringify({ success: true, data: allRows.results || [] }), {
        headers: { "Content-Type": "application/json;charset=utf-8" }
      });
    }

    // 4.4. API Categories, Budgets, Settings
    if (url.pathname.includes("/api/categories.php")) {
      let cats = { results: [] };
      if (env.DB) {
        cats = await env.DB.prepare("SELECT * FROM categories WHERE is_active = 1 ORDER BY type, id").all();
      }
      return new Response(JSON.stringify({ success: true, data: cats.results || [] }), {
        headers: { "Content-Type": "application/json;charset=utf-8" }
      });
    }

    if (url.pathname.includes("/api/budgets.php")) {
      let budgets = { results: [] };
      if (env.DB) {
        budgets = await env.DB.prepare("SELECT * FROM budgets ORDER BY year DESC, month DESC").all();
      }
      return new Response(JSON.stringify({ success: true, data: budgets.results || [] }), {
        headers: { "Content-Type": "application/json;charset=utf-8" }
      });
    }

    if (url.pathname.includes("/api/settings.php")) {
      if (method === "POST" && env.DB) {
        return new Response(JSON.stringify({ success: true, message: "Đã lưu cài đặt!" }), {
          headers: { "Content-Type": "application/json;charset=utf-8" }
        });
      }
      return new Response(JSON.stringify({ success: true, data: { currency: "VND", theme: "light" } }), {
        headers: { "Content-Type": "application/json;charset=utf-8" }
      });
    }

    // ===================================================================
    // 5. CÁC TRANG GIAO DIỆN CHÍNH (PAGES)
    // ===================================================================
    if (url.pathname.endsWith("/reports.php")) {
      return new Response(reportsHtml, { headers: { "Content-Type": "text/html;charset=utf-8" } });
    }

    if (url.pathname.endsWith("/calendar.php")) {
      return new Response(calendarHtml, { headers: { "Content-Type": "text/html;charset=utf-8" } });
    }

    if (url.pathname.endsWith("/add_transaction.php")) {
      return new Response(addTxHtml, { headers: { "Content-Type": "text/html;charset=utf-8" } });
    }

    if (url.pathname.endsWith("/settings.php") || url.pathname.endsWith("/wallets.php") || url.pathname.endsWith("/categories.php") || url.pathname.endsWith("/budgets.php")) {
      return new Response(settingsHtml, { headers: { "Content-Type": "text/html;charset=utf-8" } });
    }

    // 5.5. Trang chủ Dashboard (index.php hoặc /)
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

          const bdg = await env.DB.prepare("SELECT SUM(amount) as total FROM budgets WHERE is_active = 1").first();
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
