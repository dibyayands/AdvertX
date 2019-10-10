`include "spi_defines.v"
`include "timescale.v"

module spi_flash_shift 
  (
   clk, rst, latch, byte_sel, len, go,
   pos_edge, neg_edge,
   lsb, rx_negedge, tx_negedge,
   tip, last, 
   p_in, p_out, s_clk, s_in, s_out);
   
  parameter Tp = 1;
  
   input                          clk;          // system clock
   input                          rst;          // reset
   input 			  latch;        // latch signal for storing the data in shift register
   input [3:0] 			  byte_sel;     // byte select signals for storing the data in shift register
   input [`SPI_CHAR_LEN_BITS-1:0] len;          // data len in bits (minus one)
   input                          lsb;          // lbs first on the line
   input 			  tx_negedge;
   input 			  rx_negedge;
   input                          go;           // start stansfer
   input                          pos_edge;     // recognize posedge of sclk
   input                          neg_edge;     // recognize negedge of sclk
   output                         tip;          // transfer in progress
   output                         last;         // last bit
   input [31:0] 		  p_in;         // parallel in
   output [`SPI_MAX_CHAR-1:0] 	  p_out;        // parallel out
   input                          s_clk;        // serial clock
   input                          s_in;         // serial in
   output                         s_out;        // serial out
                                               
   reg                            s_out;        
   reg                            tip;
   
   reg [`SPI_CHAR_LEN_BITS:0] 	  cnt;          // data bit count
   reg [`SPI_MAX_CHAR-1:0] 	  data;         // shift register
   wire [`SPI_CHAR_LEN_BITS:0] 	  tx_bit_pos;   // next bit position
   wire [`SPI_CHAR_LEN_BITS:0] 	  rx_bit_pos;   // next bit position
   wire                           rx_clk;       // rx clock enable
   wire                           tx_clk;       // tx clock enable
   

   assign p_out = data;
  
   assign tx_bit_pos = lsb ? {!(|len), len} - cnt : cnt - {{`SPI_CHAR_LEN_BITS{1'b0}},1'b1};
   assign rx_bit_pos = lsb ? {!(|len), len} - (rx_negedge ? cnt + {{`SPI_CHAR_LEN_BITS{1'b0}},1'b1} : cnt) : (rx_negedge ? cnt : cnt - {{`SPI_CHAR_LEN_BITS{1'b0}},1'b1});
  
  assign last = !(|cnt);

  assign rx_clk = (rx_negedge ? neg_edge : pos_edge) && (!last || s_clk);

  assign tx_clk = (tx_negedge ? neg_edge : pos_edge) && !last;
  
  // Character bit counter
  always @(posedge clk or posedge rst)
  begin
    if(rst)
      cnt <= #Tp {`SPI_CHAR_LEN_BITS+1{1'b0}};
    else
      begin
        if(tip)
          cnt <= #Tp pos_edge ? (cnt - {{`SPI_CHAR_LEN_BITS{1'b0}}, 1'b1}) : cnt;
        else
          cnt <= #Tp !(|len) ? {1'b1, {`SPI_CHAR_LEN_BITS{1'b0}}} : {1'b0, len};
      end
  end
  
  // Transfer in progress
  always @(posedge clk or posedge rst)
  begin
    if(rst)
      tip <= #Tp 1'b0;
  else if(go && ~tip)
    tip <= #Tp 1'b1;
  else if(tip && last && pos_edge)
    tip <= #Tp 1'b0;
  end
  
  // Sending bits to the line
  always @(posedge clk or posedge rst)
  begin
    if (rst)
      s_out   <= #Tp 1'b0;
    else
      s_out <= #Tp (tx_clk || !tip) ? data[tx_bit_pos[`SPI_CHAR_LEN_BITS-1:0]] : s_out;
  end
  
   // Receiving bits from the line
   always @(posedge clk or posedge rst)
     if (rst)
       data   <= #Tp `SPI_CHAR_RST;
     else
       if (latch & !tip)
	 begin
            if (byte_sel[0])
              data[7:0] <= #Tp p_in[7:0];
            if (byte_sel[1])
              data[15:8] <= #Tp p_in[15:8];
            if (byte_sel[2])
              data[23:16] <= #Tp p_in[23:16];
            if (byte_sel[3])
              data[31:24] <= #Tp p_in[31:24];
	 end
       else
	 data[rx_bit_pos[`SPI_CHAR_LEN_BITS-1:0]] <= #Tp rx_clk ? s_in : data[rx_bit_pos[`SPI_CHAR_LEN_BITS-1:0]];
   
endmodule

