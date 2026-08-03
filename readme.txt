=== COLECTIA Notion Sync ===
Requires at least: 5.8
Requires PHP: 7.2
Stable tag: 1.43.0
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

== 1.43.0 ==
* Đổi Vật liệu cùng nhóm thành Vật liệu liên quan: ưu tiên cùng Bộ sưu tập, sau đó mới bổ sung cùng Nhóm vật liệu.
* Giới hạn một hàng, tối đa 6 vật liệu.

== 1.42.9 ==
* Căn giữa logo và tiêu đề Yêu cầu báo giá trong bản in.
* Thêm tên khách hàng và email từ tài khoản đăng nhập vào phần thông tin báo giá.

== 1.42.8 ==
* Chuẩn hóa button Cart và My Account theo thiết kế Dashboard Dự án: cao 42px, chữ 10px, uppercase, spacing và hover đồng nhất.
* Sửa markup nút Xuất yêu cầu báo giá để áp dụng đúng style.
* Chuẩn hóa button form tại Thông tin trên mobile.

== 1.42.7 ==
* Sửa crash cart khi đổi số lượng; làm cứng link xuất báo giá; tăng PRODUCT LIST và xóa tổng cộng giỏ hàng.

== 1.42.6 ==
* Cart: cập nhật Tổng quan dự án theo số lượng và đổi nút thành Cập nhật dự án.
* Moodboard trong cart hiển thị 3 vật liệu mỗi hàng.
* Sửa Xuất yêu cầu báo giá bằng trang in server ổn định.
* Thu gọn PRODUCT LIST và tăng cường responsive mobile toàn bộ cart, moodboard, action và bản in.

== 1.42.5 ==
* Giảm giới hạn che phủ còn tối đa 30% diện tích material nhỏ hơn.
* Auto Scale theo số lượng: các sample thu nhỏ đồng tỷ lệ khi Moodboard có nhiều material để luôn hiển thị đầy đủ.
* Listing đổi thành 6 cột trên mỗi hàng.

== 1.42.4 ==
* Tạo lại Moodboard dùng thuật toán random mới, biến đổi bố cục mạnh hơn ở mỗi lần bấm.
* Giới hạn phần giao nhau dưới 50% diện tích material nhỏ hơn; tránh material che phủ quá nửa material khác.

== 1.42.3 ==
* Sửa lỗi hai nút bản in không phản hồi do markup và JavaScript bị escape sai.
* Nút Tạo lại được thu nhỏ, tinh tế; In / Lưu PDF giữ vị trí và kích thước cũ.
* Tạo lại luôn đổi sang một bố cục khác và có phản hồi trạng thái.

== 1.42.2 ==
* Thêm nút Tạo lại bố cục trong bản in Moodboard.
* Nút chọn ngẫu nhiên giữa các bố cục đã kiểm soát, giữ layer và không cắt/chồng material.

== 1.42.1 ==
* Đưa Gỗ và Kim loại vào các vị trí tách xa nhau bên trong Preview, không sát mép và không bị cắt.
* Tăng thêm 20% kích thước sample Gỗ.

== 1.42.0 ==
* Sửa layer Moodboard: Vải/Da và Đá luôn nằm trên; Gỗ và Kim loại luôn nằm dưới cả ảnh lẫn bóng đổ.
* Gỗ và Kim loại dùng các slot riêng, không bao giờ chồng lên nhau.

== 1.41.9 ==
* Gỗ và Kim loại luôn nằm ở dải dưới cùng của Moodboard Preview.
* Tăng kích thước sample Gỗ thêm 50%; Gỗ và Kim loại vẫn không có bóng đổ.

== 1.41.8 ==
* Căn thẳng toàn bộ sample Moodboard, không còn xoay xiên.
* Sắp lại vị trí theo khoảng cách cố định để hạn chế chồng lấp.
* Tăng cỡ chữ loại, tên và mã vật liệu trong listing.

== 1.41.7 ==
* Tăng tương phản kích thước: Vải/Da rất lớn, Đá lớn, Kim loại/Gỗ nhỏ.
* Mở rộng visual Preview, thu nhỏ listing thành lưới 5 cột.
* Tăng đáng kể bóng đổ cho Vải/Da và Đá.

== 1.41.6 ==
* Sửa tab xuất Moodboard trống: render bản in trực tiếp theo query export, không phụ thuộc điều kiện endpoint WooCommerce.
* Luôn hiển thị Preview và listing; nếu chưa có vật liệu sẽ hiển thị thông báo rõ ràng thay vì trang trắng.

== 1.41.5 ==
* Chuyển Xuất Moodboard sang trang bản in render ở server, không còn phụ thuộc JavaScript popup/iframe.
* Tab bản in luôn hiển thị preview và nút IN / LƯU PDF rõ ràng.

== 1.41.4 ==
* Xuất Moodboard nay mở bản in bằng đúng luồng popup đồng bộ như Xuất yêu cầu báo giá, tương thích Safari và tránh mất user gesture.

== 1.41.3 ==
* Sửa lỗi bấm Xuất Moodboard không hiện hộp in/PDF: in qua iframe ẩn, chờ ảnh tải xong rồi mới in, có dự phòng popup.

== 1.41.2 ==
* Preview chỉ xuất hiện khi Xuất Moodboard; trang tài khoản chỉ giữ listing vật liệu.
* Bản xuất nền trắng, Vải/Da lớn nhất với bóng nhẹ, Đá có bóng dày hơn, Kim loại/Gỗ không bóng; bố cục collage hài hòa.

== 1.41.1 ==
* Loại bỏ hoàn toàn Đơn hàng và Tải xuống khỏi My Account; các endpoint cũ tự quay về Trang tài khoản.

== 1.41.0 ==
* Moodboard: preview thumbnail, listing, nút trong action box; hiệu ứng chỉ khi xuất.

== 1.40.3 ==
* PHỤC HỒI KHẨN: quay về mã nguồn đã xác nhận ổn định để khắc phục lỗi nghiêm trọng của các bản Moodboard sau đó.

== 1.39.2 ==
* Chuyển hai nút Moodboard ra khỏi phần Mô tả và đặt trong cột thông tin bên phải của trang chi tiết vật liệu.

== 1.39.1 ==
* Thu gọn thumbnail và khoảng cách ở Dự án và Moodboard để hiển thị nhiều nội thất/vật liệu hơn trong một màn hình.
* Moodboard: 4 cột desktop, 3 cột mobile; preview Moodboard trong Dự án: 8 vật liệu desktop, 4 cột mobile.

== 1.39.0 ==
* Thiết kế lại trang My Account → Dự án thành dashboard biên tập: tổng quan, danh sách sản phẩm, số lượng, vật liệu đã chọn, moodboard và các thao tác rõ ràng.
* Không còn nhúng giao diện cart/checkout thô trong trang Dự án.

== 1.38.0 ==
* Trang vật liệu có hai nút cạnh nhau: Thêm vào Moodboard và Tới Moodboard.
* Moodboard được lưu vào tài khoản và hiển thị thành mục riêng trong My Account.
* Sắp xếp My Account: Trang tài khoản, Dự án, Moodboard, Thông tin; mục Thông tin gộp hồ sơ tài khoản và địa chỉ.

== 1.37.1 ==
* HOTFIX: sửa lỗi PHP parse error trong mã PDF báo giá khiến toàn bộ site bị lỗi nghiêm trọng.

== 1.37.0 ==
* Thiết kế lại PDF Yêu cầu báo giá theo chuẩn editorial.
* Thêm Tên và Email khách hàng đăng nhập vào góc trên bên phải PDF.
* List sản phẩm trình bày thanh thoát hơn, vật liệu đính kèm dạng pill nhỏ bo tròn nằm ngay dưới sản phẩm.
* Dời Moodboard vật liệu xuống cuối trang báo giá.

== 1.36.0 ==
* Thiết kế lại PDF Yêu cầu báo giá theo chuẩn editorial.
* Thêm Tên và Email khách hàng đăng nhập vào góc trên bên phải PDF.
* List sản phẩm trình bày thanh thoát hơn, vật liệu đính kèm dạng pill nhỏ bo tròn nằm ngay dưới sản phẩm.
* Dời Moodboard vật liệu xuống cuối trang báo giá.

== 1.35.0 ==
* Thiết kế lại PDF Yêu cầu báo giá theo bố cục editorial gọn, rõ và chuyên nghiệp hơn.
* Nút THÊM VÀO DỰ ÁN nền xám nhạt, hover chuyển trắng.

== 1.34.0 ==
* Chuyển Moodboard lên trên Tổng quan dự án; xóa tiêu đề Tổng cộng giỏ hàng còn sót.
* Nút THÊM VÀO DỰ ÁN trên Single Product dùng nền trắng như thẻ chọn vật liệu.

== 1.33.0 ==
* Đổi thông báo giỏ hàng thành Dự án, sửa tổng số lượng danh mục realtime và cải thiện PRODUCT LIST.
* Thêm Moodboard tổng từ vật liệu báo giá và nút THÊM VÀO MOODBOARD trên Single Material.

== 1.32.0 ==
* Thêm Tổng quan dự án theo danh mục và số lượng; đổi nút thành Xuất yêu cầu báo giá.
* Single Product dùng THÊM VÀO DỰ ÁN; Cart thay checkout steps bằng PRODUCT LIST.

== 1.31.0 ==
* Số lượng và ghi chú chỉ chỉnh trong Dự án; Single Product không còn hai trường này.
* Bỏ giá/tạm tính, thay nút Tải xuống bằng Thêm vào dự án, bỏ Reviews và thêm mục Dự án trong My Account.

== 1.30.0 ==
* Thêm số lượng và ghi chú riêng cho từng sản phẩm; tự chuyển đến Dự án sau khi thêm.
* Đổi Cart thành Dự án và chặn luồng checkout; đồng bộ font nút Thêm vào dự án với Xuất PDF báo giá.

== 1.29.0 ==
* Cart bỏ giá/tạm tính, có trường ghi chú dự án và đưa nút Thêm vào dự án cạnh Xuất PDF báo giá.
* Thiết kế lại PDF báo giá dự án với ghi chú, hierarchy rõ ràng và layout refined hơn.

== 1.28.0 ==
* Đổi Add to Project thành Thêm vào dự án, bỏ số lượng trên Product và Cart.
* Quotation chỉ hiển thị vật liệu khách đã chọn, với layout nhỏ hơn hàng sản phẩm; thiết kế lại Cart theo giao diện COLECTIA.

== 1.27.0 ==
* Khôi phục Add to Cart với nhãn Add to Project và thay nút Checkout bằng Xuất báo giá dự án, tổng hợp sản phẩm và vật liệu trong giỏ.

== 1.26.0 ==
* PDF báo giá dùng hoàn toàn font không chân theo branding COLECTIA; footer nằm trong khổ trang A4.

== 1.25.0 ==
* Thiết kế lại PDF báo giá theo phong cách COLECTIA, thêm logo branding, layout A4 và typography cao cấp.

== 1.24.0 ==
* Sửa lỗi JavaScript làm vật liệu không thể chọn và nút PDF không hiển thị.
* Tinh chỉnh typography, spacing và trạng thái chọn vật liệu.

== 1.23.0 ==
* Khách chọn tối đa một vật liệu theo từng Loại và xuất PDF báo giá có thông tin sản phẩm, ảnh và vật liệu đã chọn.

== 1.22.0 ==
* Đồng bộ Plugin Header Version, phiên bản nội bộ và Stable tag để WordPress nhận diện cập nhật chính xác.

== 1.21.0 ==
* Lưu Material ID WordPress riêng cho Product để danh sách chọn không bị mất khi mở lại trang chỉnh sửa.
* Đồng bộ từ Notion cũng cập nhật Material ID cục bộ.

== 1.20.0 ==
* Box chọn vật liệu trong Product admin hiển thị thumbnail của từng vật liệu.

== 1.19.0 ==
* Bỏ hẳn key tab WoodMart chon-vat-lieu; chỉ dùng tab cns_materials.

== 1.18.0 ==
* Sửa trùng tab Vật liệu trên WoodMart: nhận diện tab theme chon-vat-lieu và thay nội dung tab đó.

== 1.17.0 ==
* Thư viện hiển thị theo Loại vật liệu; mỗi Bộ sưu tập nằm bên trong đúng Loại của nó.
* Product admin cho phép chọn vật liệu và đồng bộ ngược Relation Vật liệu sang Notion khi lưu.

== 1.16.0 ==
* Khắc phục lỗi nghiêm trọng khi tải plugin.

== 1.15.0 ==
* Tăng kích thước tên vật liệu, cải thiện khoảng cách lưới và bỏ bộ lọc Bộ sưu tập.
* Thêm box Vật liệu liên kết trong trang quản lý Product.

== 1.14.0 ==
* Nút Chọn vật liệu chỉ mở tab Vật liệu, không ảnh hưởng liên kết điều hướng khác.

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

