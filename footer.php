<?php
// footer.php
// ----------------------
// এই অংশ PDF export এর জন্য ব্যবহৃত হবে
// ----------------------
$FOOTER_INFO = [
  'org_name' => 'Lazz Pharma',
  'address'  => 'Lazz Center, 63/C, Lake Circus, Kalabagan, Dhaka.',
  'mobile'   => '+8801886886041',
  'phone'    => '+8801319864049',
  'email'    => 'lazzcorporate@gmail.com',
  'logo'     => __DIR__ . '/lp-logo.png', // optional logo path
];

if (defined('FOOTER_EXPORT_INFO') && FOOTER_EXPORT_INFO === true) {
  return $FOOTER_INFO; // শুধুই array রিটার্ন হবে যদি PDF export থেকে call করা হয়
}
?>

<!-- ----------------------
     নিচের অংশ ওয়েবসাইটে footer হিসেবে render হবে
     ---------------------- -->
<style>
  .footer {
    background-color: #138f44;
    color: white;
    font-family: Arial, sans-serif;
    padding: 8px 20px;
    position: fixed;     
    bottom: 0;
    left: 0;
    width: 100%;
    height: 0.6in;        /* fixed 0.6 inch height */
    box-sizing: border-box;
    overflow: hidden;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .footer-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex: 1;
    max-width: 1200px;
    margin: 0 auto;
    width: 100%;
  }
  .footer-column {
    flex: 1;
    min-width: 0;
    padding: 0 10px;
    font-size: 12px;
  }
  .footer-logo {
    width: 80px;
    margin-bottom: 4px;
  }
  .footer-column h3 {
    font-size: 13px;
    margin: 4px 0;
  }
  .footer-column p,
  .footer-column ul {
    margin: 2px 0;
    font-size: 11px;
  }
  .social-icons img {
    width: 18px;
    margin-right: 6px;
    transition: transform 0.3s;
  }
  .social-icons img:hover {
    transform: scale(1.2);
  }
  .footer-bottom {
    text-align: center;
    font-size: 11px;
    margin-top: 4px;
    border-top: 1px solid rgba(255,255,255,0.3);
    padding-top: 3px;
  }
</style>

<footer class="footer">
  <div class="footer-container">

    <!-- Column 1: Company Info -->
    <div class="footer-column">
      <img src="lp-logo.png" alt="Lazz Pharma" class="footer-logo">
      <p><strong>Address:</strong> Lazz Center, 63/C, Lake Circus, Kalabagan, Dhaka.</p>
      <p><strong>Mobile:</strong> +8801886886041</p>
    </div>

    <!-- Column 2: Contact Info -->
    <div class="footer-column">
      <p><strong>Email:</strong> lazzcorporate@gmail.com</p>
      <p><strong>Phone:</strong> +8801319864049</p>
      <p><strong>Support:</strong> 24/7 Available</p>
    </div>

    <!-- Column 3: Developer credit -->
    <div class="footer-column">
      <div class="footer-bottom">
        Pharmacy Software & Website Developed By - 
        <strong>Md Mokhlesur Rahman Momin</strong> (Id 23203139 )<br>
        <p> sec A</p>

      </div>
    </div>

  </div>
</footer>
