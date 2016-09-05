<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"
                         xmlns:p="http://schemas.wiredge.se/payment/api/v2/objects"
                         xmlns:a="http://schemas.wiredge.se/payment/api/v2">
  <s:Body>
    <a:AuthorizeSubscription>
      <a:data>
        <p:Amount><?php echo $data['Amount']; ?></p:Amount>
        <p:Checksum><?php echo $data['Checksum']; ?></p:Checksum>
        <p:ClientIpAddress><?php echo $data['ClientIpAddress']; ?></p:ClientIpAddress>
        <p:Currency><?php echo $data['Currency']; ?></p:Currency>
        <p:OrderReference><?php echo $data['OrderReference']; ?></p:OrderReference>
        <p:ServiceName><?php echo $data['ServiceName']; ?></p:ServiceName>
        <p:SubscriptionId><?php echo $data['SubscriptionId']; ?></p:SubscriptionId>
      </a:data>
    </a:AuthorizeSubscription>
  </s:Body>
</s:Envelope>