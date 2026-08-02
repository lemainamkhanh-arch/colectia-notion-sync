=== COLECTIA Notion Sync ===
Requires at least: 5.8
Requires PHP: 7.2
Stable tag: 1.13.0
License: GPLv2 or later

Đồng bộ sản phẩm từ Notion database "Furniture Design" sang WooCommerce.

== QUAN TRỌNG: chia sẻ thêm 2 database liên quan ==
Ngoài database Furniture Design, hãy mở từng database sau → menu ••• → Connections → thêm integration "Notion Sync", nếu không thì Danh mục và Designer sẽ đồng bộ trống dù cột đã điền:
- Category Furniture (database danh mục sản phẩm)
- Designer (database Designer)

== Hiển thị nội dung sản phẩm ==
Nội dung đồng bộ từ Notion được bọc trong khung .cns-notion-content, giới hạn bề rộng 820px và căn giữa — không còn tràn full màn hình. Ảnh, tiêu đề, danh sách, bảng, trích dẫn đều được chuẩn hoá khoảng cách và cỡ chữ cho dễ đọc, tự co giãn trên mobile.
Muốn đổi bề rộng, thêm vào Appearance → Customize → Additional CSS:
.cns-notion-content{max-width:960px;}

== Tab Vật liệu trên trang sản phẩm ==
Trong database Furniture Design có cột quan hệ "Vật liệu" liên kết trực tiếp tới database Material.
Chọn một hoặc nhiều vật liệu cho sản phẩm → plugin sẽ hiển thị chúng thành lưới swatch trong tab "Vật liệu" ở trang chi tiết sản phẩm.
- Lưới được nhóm theo MATERIAL TYPE (WOOD, LEATHER, FABRIC, METAL, NATURAL STONE...).
- Mỗi ô hiển thị: ảnh Thumbnail, tên vật liệu và mã CODE (fallback FACTORY CODE).
- Nếu vật liệu chưa có Thumbnail, plugin hiển thị ô màu theo cột "Màu sắc".
- Nếu vật liệu có URL, ô swatch trở thành link mở tab mới.
- Plugin tự gắn vào tab "Vật liệu" có sẵn của theme; nếu theme chưa có tab này, plugin tự tạo tab mới.
- Ảnh thumbnail được tải về Media Library của WordPress (link file Notion hết hạn sau ~1 giờ nên không dùng trực tiếp được).
LƯU Ý: phải share integration "Notion Sync" với cả database Material.

== Cách hoạt động ==
1. Trong Notion, tick checkbox "Đăng lên web" ở dòng sản phẩm muốn đăng.
2. Plugin chạy mỗi 15 phút (hoặc bấm "Đồng bộ ngay" trong Settings → Notion Sync).
3. Plugin tạo/cập nhật sản phẩm WooCommerce, map đầy đủ các trường:
   - Name → tên sản phẩm
   - Giá bán → giá niêm yết (regular price)
   - SKU / Mã sản phẩm → SKU
   - Tình trạng kho (Còn hàng / Hết hàng / Đặt trước) → trạng thái tồn kho
   - Trọng lượng (kg), Dài/Rộng/Cao (cm) → cân nặng & kích thước vận chuyển
   - Category Furniture → danh mục, lấy TÊN TIẾNG VIỆT từ cột "Vietnamese" của database Category Furniture (tự tạo danh mục nếu chưa có)
   - Designer → thuộc tính hiển thị (Brand KHÔNG đồng bộ)
   - COLECTIA → mô tả ngắn
   - Nội dung BÊN TRONG trang sản phẩm Notion (đoạn văn, tiêu đề, danh sách, ảnh, bảng, quote…) → "Mô tả" (content) đầy đủ của sản phẩm. Nếu trang trống, fallback dùng property "Product Fact Sheet".
   - Files & media → ảnh ĐẦU TIÊN làm ẢNH BÌA (featured), các ảnh sau làm gallery (tối đa 6 ảnh). Nếu property này trống, plugin tự lấy các ảnh chèn trong nội dung trang làm ảnh sản phẩm.
   - Bỏ tick "Đăng lên web" → sản phẩm chuyển về draft (ẩn khỏi web)

Ghi chú: khi nâng cấp plugin lên bản có trường mới, lần "Đồng bộ ngay" đầu tiên sau khi cập nhật sẽ tự đồng bộ lại TẤT CẢ sản phẩm đã sync trước đó (kể cả khi trang Notion không đổi) để lấy các trường mới, sau đó quay lại chế độ chỉ đồng bộ trang có thay đổi.
4. Plugin ghi ngược về Notion: WP Product ID, WP Link, Trạng thái sync = "Đã sync".

== Cài đặt ==
1. wp-admin → Plugins → Add New → Upload Plugin → chọn file zip này → Activate.
2. Tạo Notion integration tại https://www.notion.so/profile/integrations (Internal, quyền Read + Update + Insert content).
3. Trong Notion, mở database Furniture Design → menu "•••" → Connections → thêm integration vừa tạo.
4. wp-admin → Settings → Notion Sync → dán token → Lưu → bấm "Đồng bộ ngay" để test.

== 1.13.0 ==
* Tab Vật liệu dùng relation từ Furniture Design, thay hoàn toàn tab cũ và nhóm theo Bộ sưu tập vật liệu.
* Nút Chọn vật liệu tự mở tab Vật liệu.
* Tối ưu lưới vật liệu trong trang sản phẩm.

== 1.12.0 ==
* Tạo database Bộ sưu tập vật liệu riêng với Thumbnail, Mô tả, Loại vật liệu và trạng thái Đồng bộ Web.
* Vật liệu liên kết tới bộ sưu tập bằng Relation; ảnh Thumbnail được đồng bộ cho card bộ sưu tập trên web.
* /thu-vien-vat-lieu/ tự chia lưới theo từng bộ sưu tập khi không lọc.

== 1.11.0 ==
* Bộ lọc thư viện vật liệu tại /thu-vien-vat-lieu/ (hỗ trợ ?loai=... và ?bst=...).
* Taxonomy mới "Bộ sưu tập vật liệu" đồng bộ từ Notion.
* Nhóm vật liệu hiển thị tiếng Việt: Vải / Đá / Da / Kim loại / Gỗ.
* Tên vật liệu trong lưới nhỏ hơn 50%.
* Shortcode [colectia_materials] thêm tham số collection="...".

== 1.10.0 ==
* Tự cập nhật trực tiếp từ GitHub release (Update URI + update_plugins_github.com).
* Tự flush rewrite rules mỗi khi đổi phiên bản nên không còn lỗi 404 trang vật liệu sau khi nâng cấp.
* Thêm thẻ "Nguồn code: GitHub" trong trang cài đặt để kiểm tra bản mới.

== 1.9.1 ==
* Lưới vật liệu dùng đúng markup và class của WoodMart (wd-product / product-grid-item / wd-entities-title) nên trông giống hệt lưới sản phẩm nội thất.
* Mặc định 6 cột, tự xuống 4 cột tablet và 2 cột mobile.
* Tên nhóm vật liệu hiển thị dưới tên như danh mục sản phẩm và có link tới trang nhóm.
* Thêm tham số style="plain" nếu muốn giữ lưới đơn giản cũ.

== 1.9.0 ==
* Layout Vật liệu áp dụng cho mọi trang vật liệu: trang chi tiết, trang lưu trữ /thu-vien-vat-lieu/ và trang nhóm /nhom-vat-lieu/...
* Trang chi tiết vật liệu có breadcrumb, ảnh lớn, bảng thông số, mô tả, mục Nội thất sử dụng vật liệu này và Vật liệu cùng nhóm.
* Hỗ trợ Elementor: post type Vật liệu được bật cho Elementor, và Query ID "cns_materials" dùng cho widget Posts / Loop Grid để hiển thị vật liệu như lưới sản phẩm.
* Trang lưu trữ vật liệu hiển thị toàn bộ, nhóm theo loại vật liệu.

== 1.8.0 ==
* Chế độ catalog: bỏ hẳn giá (không đồng bộ cột Giá bán), ẩn giá và nút mua hàng, bỏ script cart fragments.
* Vật liệu tách thành post type riêng "Vật liệu" (colectia_material) với taxonomy "Nhóm vật liệu" (material_type), không còn lẫn với sản phẩm WooCommerce.
* Shortcode [colectia_materials type="..." columns="4" group="yes"] để nhúng lưới vật liệu vào trang hoặc HTML block.
* Nút chuyển toàn bộ sản phẩm trong danh mục VẬT LIỆU sang post type mới.
* Tab Vật liệu ở trang sản phẩm lấy dữ liệu trực tiếp từ post type Vật liệu và liên kết tới trang chi tiết.

== 1.7.1 ==
* Giới hạn nội dung tất cả tab ở trang single product về khối 800px canh giữa (thay vì full width), đồng bộ với container Elementor.
* Có thể đổi độ rộng bằng option cns_tab_width hoặc filter cns_tab_content_width.
* Class .cns-full cho phép một khối cụ thể tràn full width khi cần.

== 1.7.0 ==
* Dựng layout Elementor (product-post) tự động cho mỗi sản phẩm theo mẫu trang Camaleonda: khối văn bản rộng 800px, ảnh ngang full khối, ảnh dọc ghép 40/60 với đoạn văn bản kế tiếp.
* Đổ dữ liệu vào các trường ACF có sẵn: kich_thuoc_-_cau_tạo, chọn_vật_liệu, 3d_model.
* Ảnh trong nội dung Notion được tải vào Media Library và cache theo block id.

== 1.6.0 ==
* Thêm trường Status "Đồng bộ Web" (Chưa bắt đầu / Chỉnh sửa / Cập nhật) làm cơ chế kích hoạt đồng bộ theo vòng lặp.
* Đồng bộ database Material → sản phẩm trong danh mục VẬT LIỆU (tự map với sản phẩm cũ khi tên gần trùng).
* Đồng bộ database Collection ↔ taxonomy Bộ sưu tập (product_brand), map theo tên gần trùng.
* Sản phẩm nội thất được gán Bộ sưu tập từ quan hệ Furniture Collection.

