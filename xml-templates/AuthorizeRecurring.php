<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" 
 		    xmlns:p="http://schemas.wiredge.se/payment/api/v2/objects" 
		    xmlns:a="http://schemas.wiredge.se/payment/api/v2">
  <s:Body>
    <a:AuthorizeRecurring>
      <a:data>
        <p:Amount><?php echo $data['Amount']; ?></p:Amount>
        <p:CallbackUrl></p:CallbackUrl>
        <p:CardId><?php echo $data['CardId']; ?></p:CardId>
        <p:CardPassword></p:CardPassword>
        <p:Checksum><?php echo $data['Checksum']; ?></p:Checksum>
        <p:ClientIpAddress><?php echo $data['ClientIpAddress']; ?></p:ClientIpAddress>
        <p:Currency><?php echo $data['Currency']; ?></p:Currency>
        <p:CustomerReference><?php echo $data['CustomerReference']; ?></p:CustomerReference>
        <p:IsSubscription></p:IsSubscription>
        <p:OrderCategory></p:OrderCategory>
        <p:OrderReference><?php echo $data['OrderReference']; ?></p:OrderReference>
        <p:ServiceName><?php echo $data['ServiceName']; ?></p:ServiceName>
      </a:data>
    </a:AuthorizeRecurring>
  </s:Body>
</s:Envelope>