
module minsoc_onchip_ram_top ( 
  wb_clk_i, wb_rst_i, 
 
  wb_dat_i, wb_dat_o, wb_adr_i, wb_sel_i, wb_we_i, wb_cyc_i, 
  wb_stb_i, wb_ack_o, wb_err_o 
); 
 
// 
// Parameters 
//
parameter    adr_width = 13;		//Memory address width, is composed by blocks of aw_int, is not allowed to be less than 12
localparam    aw_int = 11;       	//11 = 2048
localparam    blocks = (1<<(adr_width-aw_int)); //generated memory contains "blocks" memory blocks of 2048x32 2048 depth x32 bit data

// 
// I/O Ports 
// 
input      wb_clk_i; 
input      wb_rst_i; 
 
// 
// WB slave i/f 
// 
input  [31:0]   wb_dat_i; 
output [31:0]   wb_dat_o; 
input  [31:0]   wb_adr_i; 
input  [3:0]    wb_sel_i; 
input      wb_we_i; 
input      wb_cyc_i; 
input      wb_stb_i; 
output     wb_ack_o; 
output     wb_err_o; 
 
// 
// Internal regs and wires 
// 
wire    we; 
wire [3:0]  be_i; 
wire [31:0]  wb_dat_o; 
reg    ack_we; 
reg    ack_re; 
// 
// Aliases and simple assignments 
// 
assign wb_ack_o = ack_re | ack_we; 
assign wb_err_o = wb_cyc_i & wb_stb_i & (|wb_adr_i[23:adr_width+2]);  // If Access to > (8-bit leading prefix ignored) 
assign we = wb_cyc_i & wb_stb_i & wb_we_i & (|wb_sel_i[3:0]); 
assign be_i = (wb_cyc_i & wb_stb_i) * wb_sel_i; 
 
// 
// Write acknowledge 
// 
always @ (negedge wb_clk_i or posedge wb_rst_i) 
begin 
if (wb_rst_i) 
    ack_we <= 1'b0; 
  else 
  if (wb_cyc_i & wb_stb_i & wb_we_i & ~ack_we) 
    ack_we <= #1 1'b1; 
  else 
    ack_we <= #1 1'b0; 
end 
 
// 
// read acknowledge 
// 
always @ (posedge wb_clk_i or posedge wb_rst_i) 
begin 
  if (wb_rst_i) 
    ack_re <= 1'b0; 
  else 
  if (wb_cyc_i & wb_stb_i & ~wb_err_o & ~wb_we_i & ~ack_re) 
    ack_re <= #1 1'b1; 
  else 
    ack_re <= #1 1'b0; 
end 

//Generic (multiple inputs x 1 output) MUX
localparam mux_in_nr = blocks;
localparam slices = adr_width-aw_int;
localparam mux_out_nr = blocks-1;

wire [31:0] int_dat_o[0:mux_in_nr-1];
wire [31:0] mux_out[0:mux_out_nr-1];

generate
genvar j, k;
	for (j=0; j<slices; j=j+1) begin : SLICES
		for (k=0; k<(mux_in_nr>>(j+1)); k=k+1) begin : MUX
			if (j==0) begin
				mux2 #
                (
                    .dw(32)
                ) 
                mux_int(
                    .sel( wb_adr_i[aw_int+2+j] ), 
                    .in1( int_dat_o[k*2] ),
				    .in2( int_dat_o[k*2+1] ), 
                    .out( mux_out[k] )
                );
			end
			else begin
				mux2 #
                (
                    .dw(32)
                ) 
                mux_int(
                    .sel( wb_adr_i[aw_int+2+j] ), 
				    .in1( mux_out[(mux_in_nr-(mux_in_nr>>(j-1)))+k*2] ), 
				    .in2( mux_out[(mux_in_nr-(mux_in_nr>>(j-1)))+k*2+1] ), 
				    .out( mux_out[(mux_in_nr-(mux_in_nr>>j))+k] )
                );
			end
		end
	end
endgenerate

//last output = total output
assign wb_dat_o = mux_out[mux_out_nr-1];

//(mux_in_nr-(mux_in_nr>>j)): 
//-Given sum of 2^i | i = x -> y series can be resumed to 2^(y+1)-2^x
//so, with this expression I'm evaluating how many times the internal loop has been run

wire [blocks-1:0] bank;
 
generate 
genvar i;
    for (i=0; i < blocks; i=i+1) begin : MEM

        assign bank[i] = wb_adr_i[adr_width+1:aw_int+2] == i;

        //BANK0
        minsoc_onchip_ram block_ram_0 ( 
            .clk(wb_clk_i), 
            .rst(wb_rst_i),
            .addr(wb_adr_i[aw_int+1:2]), 
            .di(wb_dat_i[7:0]), 
            .doq(int_dat_o[i][7:0]), 
            .we(we & bank[i]), 
            .oe(1'b1),
            .ce(be_i[0])
        ); 


        minsoc_onchip_ram block_ram_1 ( 
            .clk(wb_clk_i), 
            .rst(wb_rst_i),
            .addr(wb_adr_i[aw_int+1:2]), 
            .di(wb_dat_i[15:8]), 
            .doq(int_dat_o[i][15:8]), 
            .we(we & bank[i]), 
            .oe(1'b1),
            .ce(be_i[1])
        ); 

        minsoc_onchip_ram block_ram_2 ( 
            .clk(wb_clk_i), 
            .rst(wb_rst_i),
            .addr(wb_adr_i[aw_int+1:2]), 
            .di(wb_dat_i[23:16]), 
            .doq(int_dat_o[i][23:16]), 
            .we(we & bank[i]), 
            .oe(1'b1),
            .ce(be_i[2])
        ); 

        minsoc_onchip_ram block_ram_3 ( 
            .clk(wb_clk_i), 
            .rst(wb_rst_i),
            .addr(wb_adr_i[aw_int+1:2]), 
            .di(wb_dat_i[31:24]), 
            .doq(int_dat_o[i][31:24]), 
            .we(we & bank[i]), 
            .oe(1'b1),
            .ce(be_i[3])
        ); 

    end
endgenerate

endmodule 

module mux2(sel,in1,in2,out);

parameter dw = 32;

input sel;
input [dw-1:0] in1, in2;
output reg [dw-1:0] out;

always @ (sel or in1 or in2)
begin
	case (sel)
		1'b0: out = in1;
		1'b1: out = in2;
	endcase
end

endmodule
